<?php
/**
 * Test de adaptadores Destacamos y Mundosex
 * Ejecutar: php tools/test_adapters.php
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

// ─── CONFIG ────────────────────────────────────────────
$now = now_datetime();
$stamp = date('His');
define('TEST_DEST_ACCOUNT_ID', 'anun_1f341ede');
define('TEST_DEST_LISTING_ID', '1099414');
define('TEST_DEST_TITLE', 'Test sistema ' . $stamp);
define('TEST_DEST_DESCRIPTION', 'Prueba de subida automatica. Sistema CRM Atupuerta. ' . $now);

define('TEST_MUN_ACCOUNT_ID', 'anun_62d1ab7d');
define('TEST_MUN_LISTING_ID', '99542979');
define('TEST_MUN_TITLE', 'Test sistema ' . $stamp);
define('TEST_MUN_DESCRIPTION', 'Prueba de subida automatica via CRM Atupuerta. Verificando el correcto funcionamiento de la conexion y publicacion en mundosexanuncio.com. Test del sistema automatizado.');

echo "══════════════════════════════════════════════════\n";
echo "  TEST DE ADAPTADORES — Destacamos & Mundosex\n";
echo "  " . now_datetime() . "\n";
echo "══════════════════════════════════════════════════\n\n";

// ─── TEST 1: Destacamos ───────────────────────────────
echo "┌── TEST 1: Destacamos ──────────────────────────┐\n";

$destAccount = publicista_account_get(TEST_DEST_ACCOUNT_ID, true);
if (!$destAccount) {
    echo "│ ❌ No se encontró la cuenta Destacamos\n";
} else {
    echo "│ Cuenta: " . $destAccount['login_user'] . " (" . $destAccount['portal_code'] . ")\n";
    echo "│ Listing: " . TEST_DEST_LISTING_ID . "\n";
    echo "│ Título: " . TEST_DEST_TITLE . "\n";
    
    $payload = array(
        'username' => trim((string)($destAccount['login_user'] ?? '')),
        'password' => trim((string)($destAccount['login_pass'] ?? '')),
        'listingId' => TEST_DEST_LISTING_ID,
        'timeoutMs' => 120000,
        'save' => true,
        'debug_log' => true,
        'fields' => array(
            'title' => TEST_DEST_TITLE,
            'description' => TEST_DEST_DESCRIPTION,
        ),
        'editPhotos' => false,
        'photos' => array(),
        'humanize' => array(
            'enabled' => false,
        ),
    );
    
    require_once BASE_PATH . '/subirPublicidad/destacamos.php';
    
    $startDest = microtime(true);
    $resultDest = ejecutarAutomatizacion($payload);
    $elapsedDest = round((microtime(true) - $startDest) * 1000);
    
    echo "│ Resultado: " . ($resultDest['ok'] ? '✅ OK' : '❌ FALLÓ') . " (" . $elapsedDest . "ms)\n";
    if (!$resultDest['ok']) {
        echo "│ Error: " . ($resultDest['error'] ?? 'N/A') . "\n";
        if (!empty($resultDest['error_code'])) {
            echo "│ Código: " . $resultDest['error_code'] . "\n";
        }
        if (!empty($resultDest['validation_errors'])) {
            echo "│ Validación: " . implode('; ', $resultDest['validation_errors']) . "\n";
        }
    } else {
        echo "│ Login: " . ($resultDest['loginOk'] ? 'OK' : 'Falló') . "\n";
        echo "│ Guardado: " . ($resultDest['saveClicked'] ?? '?') . "\n";
    }
    if (!empty($resultDest['warnings'])) {
        foreach ((array)$resultDest['warnings'] as $w) {
            echo "│ ⚠ " . (is_string($w) ? $w : json_encode($w)) . "\n";
        }
    }
    if (!empty($resultDest['save_soft_mismatch'])) {
        echo "│ ⚠ Soft mismatch: " . json_encode($resultDest['save_soft_mismatch']) . "\n";
    }
}
echo "└──────────────────────────────────────────────────┘\n\n";

// ─── TEST 2: Mundosex ─────────────────────────────────
echo "┌── TEST 2: Mundosex ─────────────────────────────┐\n";

$munAccount = publicista_account_get(TEST_MUN_ACCOUNT_ID, true);
if (!$munAccount) {
    echo "│ ❌ No se encontró la cuenta Mundosex\n";
} else {
    echo "│ Cuenta: " . $munAccount['login_user'] . " (" . $munAccount['portal_code'] . ")\n";
    echo "│ Listing: " . TEST_MUN_LISTING_ID . "\n";
    echo "│ Título: " . TEST_MUN_TITLE . "\n";
    
    $payload = array(
        'username' => trim((string)($munAccount['login_user'] ?? '')),
        'password' => trim((string)($munAccount['login_pass'] ?? '')),
        'listingId' => TEST_MUN_LISTING_ID,
        'timeoutMs' => 300000,
        'save' => true,
        'debug_log' => true,
        'fields' => array(
            'title' => TEST_MUN_TITLE,
            'description' => TEST_MUN_DESCRIPTION,
        ),
        'editPhotos' => false,
        'photos' => array(),
        'humanize' => array(
            'enabled' => false,
        ),
    );
    
    require_once BASE_PATH . '/subirPublicidad/mundosex.php';
    
    if (!function_exists('mundosex_ejecutar_automatizacion')) {
        echo "│ ❌ Función mundosex_ejecutar_automatizacion no disponible\n";
    } else {
        $startMun = microtime(true);
        $resultMun = mundosex_ejecutar_automatizacion($payload);
        $elapsedMun = round((microtime(true) - $startMun) * 1000);
        
        echo "│ Resultado: " . ($resultMun['ok'] ? '✅ OK' : '❌ FALLÓ') . " (" . $elapsedMun . "ms)\n";
        if (!$resultMun['ok']) {
            echo "│ Error: " . ($resultMun['error'] ?? 'N/A') . "\n";
        } else {
            echo "│ Login: " . ($resultMun['loginOk'] ? 'OK' : 'Falló') . "\n";
            echo "│ Guardado: " . ($resultMun['saveClicked'] ? 'Sí' : 'No') . "\n";
            echo "│ Fotos: " . ($resultMun['photosUploaded'] ?? 0) . " subidas\n";
        }
        if (!empty($resultMun['warnings'])) {
            foreach ((array)$resultMun['warnings'] as $w) {
                echo "│ ⚠ " . (is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE)) . "\n";
            }
        }
        if (!empty($resultMun['adapter_meta'])) {
            echo "│ Meta: " . json_encode($resultMun['adapter_meta'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
echo "└──────────────────────────────────────────────────┘\n\n";

echo "══════════════════════════════════════════════════\n";
echo "  FIN DE TESTS\n";
echo "══════════════════════════════════════════════════\n";
