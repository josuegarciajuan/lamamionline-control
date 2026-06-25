<?php
/**
 * test_pause_regression.php — Verifica que PauseGate::isThreadPaused()
 * funcione de forma fiable para userId=1 (admin) independientemente del CWD.
 *
 * Bug: PauseGate usaba file_exists(dirname($relativo)) con ruta relativa
 * para decidir qué archivo de pausa leer. Eso dependía del CWD del proceso,
 * causando que para admin (userId=1, sin data/users/1/) leyera del archivo
 * equivocado → isThreadPaused() devolvía false → el bot seguía contestando.
 */

declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__));

// Autoloader (mismo que usa webhook.php)
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

$pass = 0;
$fail = 0;

function assert_eq($label, $expected, $actual): void {
    global $pass, $fail;
    if ($expected === $actual) {
        echo "[  PASS  ] {$label}\n";
        $pass++;
    } else {
        echo "[  FAIL  ] {$label}\n";
        echo "          expected: " . var_export($expected, true) . "\n";
        echo "          actual:   " . var_export($actual, true) . "\n";
        $fail++;
    }
}

$testThreadId = '631349504_TESTPAUSE_99999';  // unique, real-world-like last9_phone format
$rootDir = WASAPBOT_ROOT;

echo "=== Test de regresión PauseGate + userId=1 (admin) ===\n\n";

// ── 1. Bootstrap with userId=1 ────────────────────────────────────────
echo "[  INFO  ] Bootstrapping with userId=1...\n";
$instances = \WasapBot\Bot::bootstrap($rootDir, 1);
$config    = $instances['config'];
$logger    = $instances['logger'];

// ── 2. Determine where PauseGate will read from ───────────────────────
$pausedFromConfig = (string) $config->get('files.paused_threads', '');
echo "[  INFO  ] config files.paused_threads = '{$pausedFromConfig}'\n";

// ── 3. Write test paused entry ────────────────────────────────────────
// Must write to the SAME file that PauseGate will read (resolved in its constructor)
$pauseGate = new \WasapBot\Pipeline\PauseGate($config, $logger);
// We need to access the resolved path — use reflection
$ref = new ReflectionProperty($pauseGate, 'pausedFile');
$pausedFile = $ref->getValue($pauseGate);
echo "[  INFO  ] PauseGate resolved pausedFile = '{$pausedFile}'\n";

$canonicalGlobal = $rootDir . '/data/paused_threads.ndjson';
assert_eq('PauseGate reads same file as webhook/UI (global)', $canonicalGlobal, $pausedFile);

// Write the test entry
$testEntry = json_encode(['thread_id' => $testThreadId, 'paused_at' => gmdate('c')]) . "\n";
@file_put_contents($pausedFile, $testEntry, FILE_APPEND | LOCK_EX);

// ── 4. Test isThreadPaused from project root CWD ──────────────────────
chdir($rootDir);
echo "[  INFO  ] CWD = " . getcwd() . "\n";
$result1 = $pauseGate->isThreadPaused($testThreadId);
assert_eq('isThreadPaused(' . $testThreadId . ') from project root', true, $result1);

// ── 5. Test isThreadPaused from different CWD (the old bug trigger!) ──
chdir('/tmp');
echo "[  INFO  ] CWD = " . getcwd() . "\n";
$result2 = $pauseGate->isThreadPaused($testThreadId);
assert_eq('isThreadPaused(' . $testThreadId . ') from /tmp (other CWD)', true, $result2);

// ── 6. Test isThreadPaused from public/ CWD (the exact bug scenario) ──
$publicDir = $rootDir . '/public';
if (is_dir($publicDir)) {
    chdir($publicDir);
    echo "[  INFO  ] CWD = " . getcwd() . "\n";
    $result3 = $pauseGate->isThreadPaused($testThreadId);
    assert_eq('isThreadPaused(' . $testThreadId . ') from public/ (bug CWD)', true, $result3);
}

// ── 7. Cleanup: remove test entry ─────────────────────────────────────
$lines = @file($pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$kept = [];
if ($lines) {
    foreach ($lines as $l) {
        $r = json_decode($l, true);
        if (is_array($r) && ((string) ($r['thread_id'] ?? '')) === $testThreadId) {
            continue;
        }
        $kept[] = $l;
    }
}
@file_put_contents($pausedFile, implode("\n", $kept) . (count($kept) > 0 ? "\n" : ''), LOCK_EX);

echo "\n=== Resultado: {$pass} PASS, {$fail} FAIL ===\n\n";
exit($fail > 0 ? 1 : 0);
