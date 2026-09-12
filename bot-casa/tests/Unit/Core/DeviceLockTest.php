<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\DeviceLock;

final class DeviceLockTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/wasapbot_devicelock_' . uniqid('', true);
        mkdir($this->rootDir, 0700, true);
        $_COOKIE = [];
        $_SERVER['SCRIPT_NAME'] = '/control/inbox.php';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        $this->removeTree($this->rootDir);
    }

    private function lock(): DeviceLock
    {
        return new DeviceLock($this->rootDir . '/device_lock.php', 'dlk_dev');
    }

    private function fingerprint(string $seed = 'a'): string
    {
        return hash('sha256', $seed);
    }

    public function test_default_state_is_observe_without_devices(): void
    {
        $lock = $this->lock();
        $state = $lock->snapshot();

        self::assertSame(DeviceLock::MODE_OBSERVE, $lock->mode());
        self::assertSame([], $state['devices']);
        self::assertSame([], $state['candidates']);
        self::assertFalse(is_file($this->rootDir . '/device_lock.php'));
    }

    public function test_normalize_fingerprint_and_token_hash(): void
    {
        self::assertSame(hash('sha256', 'x'), DeviceLock::normalizeFingerprint(strtoupper(hash('sha256', 'x'))));
        self::assertNull(DeviceLock::normalizeFingerprint('no-es-hex'));
        self::assertNull(DeviceLock::normalizeFingerprint(12345));
        self::assertSame(hash('sha256', 'raw-token'), DeviceLock::hashToken('raw-token'));
    }

    public function test_fingerprint_from_signals_is_stable_and_ignores_volatile_keys(): void
    {
        $signals = [
            'ua' => 'Mobile',
            'platform' => 'Linux armv81',
            'langs' => ['es-ES', 'es'],
            'hw' => 8,
            'mem' => 8,
            'screen' => [393, 873, 24, 2.75],
            'tz' => 'Europe/Madrid',
            'canvas' => 'abc',
            'webgl' => 'Adreno 610',
            'audio' => 'volatile-123',
            'timestamp' => '123456',
        ];

        $first = DeviceLock::fingerprintFromSignals($signals);
        $second = DeviceLock::fingerprintFromSignals($signals);
        self::assertNotNull($first);
        self::assertSame($first, $second, 'Mismas señales => mismo fingerprint');

        // Claves volátiles añadidas no alteran el resultado.
        $withVolatile = $signals;
        $withVolatile['audio'] = 'otra-cosa-distinta';
        $withVolatile['extra'] = 'x';
        self::assertSame($first, DeviceLock::fingerprintFromSignals($withVolatile));

        // Una señal estable que cambia sí altera el fingerprint.
        $changed = $signals;
        $changed['webgl'] = 'Mali-G52';
        self::assertNotSame($first, DeviceLock::fingerprintFromSignals($changed));
    }

    public function test_fingerprint_from_signals_needs_enough_data(): void
    {
        self::assertNull(DeviceLock::fingerprintFromSignals(['ua' => 'x']));
        self::assertNull(DeviceLock::fingerprintFromSignals([]));
    }

    public function test_register_http_path_derives_fingerprint_from_signals(): void
    {
        $lock = $this->lock();
        $signals = [
            'ua' => 'Mobile',
            'platform' => 'Linux armv81',
            'langs' => ['es-ES'],
            'hw' => 8,
            'mem' => 8,
            'screen' => [393, 873, 24, 2.75],
            'tz' => 'Europe/Madrid',
            'canvas' => 'abc',
            'webgl' => 'Adreno 610',
        ];

        // fingerprintInput=null => se deriva de las señales
        $result = $lock->registerWith('inbox', null, $signals);
        self::assertTrue($result['authorized']);

        $expected = DeviceLock::fingerprintFromSignals($signals);
        self::assertNotNull($expected);
        self::assertSame($expected, $lock->snapshot()['candidates'][0]['fingerprint']);
    }

    public function test_observe_register_records_candidate_and_never_blocks(): void
    {
        $lock = $this->lock();
        $fp = $this->fingerprint('observe');

        $result = $lock->registerWith('inbox', $fp, ['platform' => 'Android']);

        self::assertTrue($result['authorized']);
        self::assertFalse($result['enforce']);

        $state = $lock->snapshot();
        self::assertCount(1, $state['candidates']);
        $candidate = $state['candidates'][0];
        self::assertSame($fp, $candidate['fingerprint']);
        self::assertArrayHasKey('inbox', $candidate['apps']);
        self::assertSame([], $state['devices']);
    }

    public function test_authorize_promotes_candidate_to_authorized_device(): void
    {
        $lock = $this->lock();
        $fp = $this->fingerprint('promote');
        $rawToken = bin2hex(random_bytes(32));
        $_COOKIE['dlk_dev'] = $rawToken;

        $lock->registerWith('inbox', $fp, []);
        $lock->registerWith('botcasa', $fp, []);

        $result = $lock->authorizeFingerprint($fp, 'Mi movil');
        self::assertTrue($result['ok']);

        $state = $lock->snapshot();
        self::assertCount(1, $state['devices']);
        self::assertTrue($state['devices'][0]['authorized']);
        self::assertSame($fp, $state['devices'][0]['fingerprint']);
        self::assertContains(DeviceLock::hashToken($rawToken), $state['devices'][0]['tokens']);
        self::assertArrayHasKey('inbox', $state['devices'][0]['apps']);
        self::assertArrayHasKey('botcasa', $state['devices'][0]['apps']);
        self::assertSame([], $state['candidates']);
    }

    public function test_enforce_allows_authorized_fingerprint_and_token(): void
    {
        $lock = $this->lock();
        $fp = $this->fingerprint('allow');
        $_COOKIE['dlk_dev'] = bin2hex(random_bytes(32));

        $lock->registerWith('inbox', $fp, []);
        $lock->authorizeFingerprint($fp);
        self::assertTrue($lock->setMode(DeviceLock::MODE_ENFORCE));
        self::assertSame(DeviceLock::MODE_ENFORCE, $lock->mode());

        $result = $lock->registerWith('inbox', $fp, []);
        self::assertTrue($result['authorized']);
        self::assertTrue($result['enforce']);

        self::assertTrue($lock->gate('inbox'));
    }

    public function test_enforce_rejects_unknown_fingerprint(): void
    {
        $lock = $this->lock();
        $known = $this->fingerprint('known');
        $unknown = $this->fingerprint('unknown');
        $_COOKIE['dlk_dev'] = bin2hex(random_bytes(32));

        $lock->registerWith('inbox', $known, []);
        $lock->authorizeFingerprint($known);
        $lock->setMode(DeviceLock::MODE_ENFORCE);

        $result = $lock->registerWith('inbox', $unknown, []);
        self::assertFalse($result['authorized']);
        self::assertTrue($result['enforce']);

        // El desconocido queda registrado como candidato para auditoría.
        $state = $lock->snapshot();
        self::assertCount(1, $state['candidates']);
        self::assertSame($unknown, $state['candidates'][0]['fingerprint']);
    }

    public function test_revoke_device_removes_it(): void
    {
        $lock = $this->lock();
        $fp = $this->fingerprint('revoke');

        $lock->registerWith('inbox', $fp, []);
        $lock->authorizeFingerprint($fp);
        $state = $lock->snapshot();
        $id = (string)$state['devices'][0]['id'];

        self::assertTrue($lock->revokeDevice($id));
        self::assertSame([], $lock->snapshot()['devices']);
        self::assertFalse($lock->revokeDevice($id));
    }

    public function test_state_file_is_not_plain_json(): void
    {
        $lock = $this->lock();
        $lock->registerWith('inbox', $this->fingerprint('prefix'), []);
        $lock->save($lock->snapshot());

        $raw = (string)file_get_contents($this->rootDir . '/device_lock.php');
        self::assertStringStartsWith('<?php', $raw);
        self::assertStringContainsString('http_response_code(404)', $raw);
        self::assertStringNotContainsString('{"version"', $raw, 'El JSON va precedido del prefijo PHP que corta el acceso web');
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
