<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\Config;
use WasapBot\Core\LineProvisioningWahaInterface;

/** Provisions only tenant-owned lines created through the public client APIs. */
final class TenantLineProvisioner
{
    public function __construct(
        private readonly string $rootDir,
        private readonly LineProvisioningWahaInterface $waha,
        private readonly string $linesMapFile,
        private readonly string $webhookUrl,
    ) {
    }

    /** @return array{ok: bool, line?: array<string, mixed>, error?: string, warning?: string} */
    public function create(string $phone, string $label, int $userId, int $requestedPort = 0): array
    {
        if ($userId <= 1) return ['ok' => false, 'error' => 'Tenant line required'];

        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        $last9 = mb_substr(strlen($digits) < 9 ? str_pad($digits, 9, '0', STR_PAD_LEFT) : $digits, -9);
        $linesFile = $this->rootDir . '/data/users/' . $userId . '/lines.json';
        $lines = $this->readArray($linesFile);
        $port = $requestedPort;
        if ($port <= 0) $port = 3020;
        if ($requestedPort <= 0) {
            try {
                $port = (int) ($this->waha->getStatus()['next_port'] ?? 3020);
            } catch (\Throwable) {
                $port = 3020;
            }
        }

        try {
            $created = $this->waha->createInstance($port);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'WAHA no disponible'];
        }
        if (empty($created['ok'])) return ['ok' => false, 'error' => (string) ($created['error'] ?? 'Error al crear instancia WAHA')];

        $actualPort = (int) ($created['port'] ?? $port);
        $webhookError = '';
        try {
            $webhook = $this->waha->configureSession($actualPort, $this->webhookUrl);
            if (empty($webhook['ok'])) $webhookError = 'No se pudo configurar el webhook WAHA';
        } catch (\Throwable $e) {
            $webhookError = 'No se pudo configurar el webhook WAHA';
        }

        if ($webhookError !== '') {
            $this->rollbackInstance($actualPort);
            return ['ok' => false, 'error' => $webhookError];
        }

        $nextId = $this->nextId($lines);
        $line = [
            'id' => $nextId,
            'last9' => $last9,
            'phone' => $phone,
            'label' => $label !== '' ? $label : 'Línea ' . $nextId,
            'port' => $actualPort,
            'container_port' => $port,
            'created_at' => date('c'),
            'health_status' => 'starting',
            'error' => '',
            'capture_native_outbound' => true,
            'webhook_configured' => $webhookError === '',
        ];
        $lines[] = $line;
        $this->writeJson($linesFile, $lines);

        $map = $this->readArray($this->linesMapFile);
        $map[$last9] = $userId;
        $this->writeJson($this->linesMapFile, $map);
        $this->syncRouting($userId, $lines);

        return ['ok' => true, 'line' => $line];
    }

    /** @return array<string|int, mixed> */
    private function readArray(string $path): array
    {
        if (!file_exists($path)) return [];
        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int|string, mixed> $lines */
    private function nextId(array $lines): int
    {
        $max = 0;
        foreach ($lines as $line) {
            if (is_array($line)) $max = max($max, (int) ($line['id'] ?? 0));
        }
        return $max + 1;
    }

    /** @param array<int|string, mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    /** @param array<int|string, mixed> $lines */
    private function syncRouting(int $userId, array $lines): void
    {
        try {
            $config = new Config(\WasapBot\Bot::resolveUserConfigDir($this->rootDir, $userId), $this->rootDir);
            $routing = [];
            foreach ($lines as $line) {
                if (!is_array($line)) continue;
                $routing[] = [
                    'last9' => (string) ($line['last9'] ?? ''),
                    'port' => (int) ($line['port'] ?? 0),
                    'label' => (string) ($line['label'] ?? ''),
                    'enabled' => true,
                    'ai_provider' => (string) ($line['ai_provider'] ?? 'openai'),
                    'ai_model' => $line['ai_model'] ?? null,
                ];
            }
            $config->set('routing.lines', $routing);
            $config->save();
        } catch (\Throwable) {
            // The line and mapping are already durable; bootstrap can recover routing.
        }
    }

    private function rollbackInstance(int $port): void
    {
        try {
            $result = $this->waha->deleteInstance($port);
            if (!empty($result['ok'])) return;
        } catch (\Throwable) {
            // Fall through to reset: both operations are best-effort cleanup.
        }
        try {
            $this->waha->resetInstance($port);
        } catch (\Throwable) {
            // The clear configuration error remains the actionable result.
        }
    }
}
