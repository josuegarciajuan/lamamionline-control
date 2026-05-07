<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/subirPublicidad/destacamos.php';

// Diagnóstico manual:
// 1. Rellena usuario/password.
// 2. Elige modo:
//    - 'login' para probar solo acceso
//    - 'listing_update' para probar login + edición + fotos
// 3. Si usas 'listing_update', rellena listingId, fields y photos.
// 4. Ejecuta: php tools/test_destacamos_login.php
// 5. Pásame el JSON resultante.
const DESTACAMOS_TEST_USER = 'tracatrack@gmail.com';
const DESTACAMOS_TEST_PASS = 'vsomnos1Q#';
const DESTACAMOS_TEST_MODE = 'listing_update'; // 'login' | 'listing_update'
const DESTACAMOS_TEST_LISTING_ID = '1049429';
const DESTACAMOS_TEST_HUMANIZE = true;
const DESTACAMOS_TEST_HUMAN_DEFAULT_MIN_MS = 900;
const DESTACAMOS_TEST_HUMAN_DEFAULT_MAX_MS = 2200;
const DESTACAMOS_TEST_HUMAN_READING_MIN_MS = 1200;
const DESTACAMOS_TEST_HUMAN_READING_MAX_MS = 2800;
const DESTACAMOS_TEST_HUMAN_CLICK_MIN_MS = 350;
const DESTACAMOS_TEST_HUMAN_CLICK_MAX_MS = 1100;
const DESTACAMOS_TEST_HUMAN_TYPING_CHAR_MIN_MS = 25;
const DESTACAMOS_TEST_HUMAN_TYPING_CHAR_MAX_MS = 65;
const DESTACAMOS_TEST_HUMAN_TYPING_EXTRA_MIN_MS = 180;
const DESTACAMOS_TEST_HUMAN_TYPING_EXTRA_MAX_MS = 700;
const DESTACAMOS_TEST_HUMAN_TYPING_MAX_MS = 4500;
const DESTACAMOS_TEST_HUMAN_PHOTO_BETWEEN_MIN_MS = 1800;
const DESTACAMOS_TEST_HUMAN_PHOTO_BETWEEN_MAX_MS = 4200;

function test_destacamos_fields() {
    return array(
         'title' => 'Lisa bombonncito atractivo',
        // 'description' => 'Descripcion de prueba',
        // 'telefono' => '600000000',
        // 'city' => 'Burriana',
        // 'localidad' => 'Burriana',
    );
}

function test_destacamos_photos() {
    return array(
         '/home/oficina/Descargas/check.png',
        // BASE_PATH . '/ruta/absoluta/a/foto2.jpg',
    );
}

function test_destacamos_debug_dir() {
    $dir = DATA_PATH . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function test_destacamos_dump_login_page($session) {
    $loginUrl = 'https://www.destacamos.net/login.php?loc=browse_listings.php';
    $resp = destacamos_http_request($session, 'GET', $loginUrl, array('timeout' => 60));

    $debug = array(
        'ok' => $resp['ok'] ?? false,
        'http_code' => $resp['http_code'] ?? 0,
        'effective_url' => $resp['effective_url'] ?? '',
        'error' => $resp['error'] ?? '',
        'content_type' => $resp['content_type'] ?? '',
        'body_length' => strlen((string)($resp['body'] ?? '')),
        'has_logout' => destacamos_page_has_logout((string)($resp['body'] ?? '')),
        'forms_detected' => 0,
        'forms' => array(),
        'saved_html_path' => '',
        'saved_json_path' => '',
    );

    list($dom, $xpath) = destacamos_html_load_xpath((string)($resp['body'] ?? ''));
    if ($dom && $xpath) {
        $forms = $xpath->query('//form');
        $debug['forms_detected'] = $forms ? $forms->length : 0;
        if ($forms) {
            foreach ($forms as $index => $formNode) {
                if (!($formNode instanceof DOMElement)) continue;
                $form = destacamos_form_build($formNode, (string)($resp['effective_url'] ?? $loginUrl));
                $debug['forms'][] = array(
                    'index' => $index,
                    'id' => $form['id'] ?? '',
                    'action_url' => $form['action_url'] ?? '',
                    'method' => $form['method'] ?? '',
                    'field_names' => array_values(array_filter(array_map(function($field) {
                        return trim((string)($field['name'] ?? ($field['id'] ?? '')));
                    }, (array)($form['fields'] ?? array())))),
                );
            }
        }
    }

    $stamp = date('Ymd_His');
    $dir = test_destacamos_debug_dir();
    $htmlPath = $dir . '/destacamos_login_debug_' . $stamp . '.html';
    $jsonPath = $dir . '/destacamos_login_debug_' . $stamp . '.json';
    @file_put_contents($htmlPath, (string)($resp['body'] ?? ''));
    @file_put_contents($jsonPath, json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $debug['saved_html_path'] = $htmlPath;
    $debug['saved_json_path'] = $jsonPath;

    return array($resp, $debug);
}

$session = destacamos_http_session(60);

try {
    if (DESTACAMOS_TEST_USER === 'PON_AQUI_EL_USUARIO' || DESTACAMOS_TEST_PASS === 'PON_AQUI_LA_PASSWORD') {
        echo json_encode(array(
            'ok' => false,
            'error' => 'Debes editar tools/test_destacamos_login.php y poner usuario y password reales en las constantes.',
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(1);
    }

    list($rawLoginResp, $rawLoginDebug) = test_destacamos_dump_login_page($session);

    $mode = trim((string)DESTACAMOS_TEST_MODE);
    $result = array(
        'ok' => false,
        'mode' => $mode,
        'user' => DESTACAMOS_TEST_USER,
        'login_page_debug' => $rawLoginDebug,
        'tested_at' => now_datetime(),
    );

    if ($mode === 'listing_update') {
        $fields = test_destacamos_fields();
        $photos = test_destacamos_photos();
        if (DESTACAMOS_TEST_LISTING_ID === 'PON_AQUI_EL_LISTING_ID') {
            echo json_encode(array(
                'ok' => false,
                'error' => 'Debes rellenar DESTACAMOS_TEST_LISTING_ID para probar listing_update.',
                'mode' => $mode,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }

        $payload = array(
            'username' => DESTACAMOS_TEST_USER,
            'password' => DESTACAMOS_TEST_PASS,
            'listingId' => trim((string)DESTACAMOS_TEST_LISTING_ID),
            'timeoutMs' => 120000,
            'humanize' => DESTACAMOS_TEST_HUMANIZE,
            'humanDefaultMinMs' => DESTACAMOS_TEST_HUMAN_DEFAULT_MIN_MS,
            'humanDefaultMaxMs' => DESTACAMOS_TEST_HUMAN_DEFAULT_MAX_MS,
            'humanReadingMinMs' => DESTACAMOS_TEST_HUMAN_READING_MIN_MS,
            'humanReadingMaxMs' => DESTACAMOS_TEST_HUMAN_READING_MAX_MS,
            'humanClickMinMs' => DESTACAMOS_TEST_HUMAN_CLICK_MIN_MS,
            'humanClickMaxMs' => DESTACAMOS_TEST_HUMAN_CLICK_MAX_MS,
            'humanTypingCharMinMs' => DESTACAMOS_TEST_HUMAN_TYPING_CHAR_MIN_MS,
            'humanTypingCharMaxMs' => DESTACAMOS_TEST_HUMAN_TYPING_CHAR_MAX_MS,
            'humanTypingExtraMinMs' => DESTACAMOS_TEST_HUMAN_TYPING_EXTRA_MIN_MS,
            'humanTypingExtraMaxMs' => DESTACAMOS_TEST_HUMAN_TYPING_EXTRA_MAX_MS,
            'humanTypingMaxMs' => DESTACAMOS_TEST_HUMAN_TYPING_MAX_MS,
            'humanPhotoBetweenMinMs' => DESTACAMOS_TEST_HUMAN_PHOTO_BETWEEN_MIN_MS,
            'humanPhotoBetweenMaxMs' => DESTACAMOS_TEST_HUMAN_PHOTO_BETWEEN_MAX_MS,
            'save' => true,
            'fields' => $fields,
            'editPhotos' => !empty($photos),
            'photos' => $photos,
        );

        $automationResult = ejecutarAutomatizacion($payload);
        $result = array_merge($result, array(
            'ok' => !empty($automationResult['ok']),
            'listing_id' => trim((string)DESTACAMOS_TEST_LISTING_ID),
            'fields_payload' => $fields,
            'photos_payload' => $photos,
            'humanize' => DESTACAMOS_TEST_HUMANIZE,
            'automation_result' => $automationResult,
            'error' => trim((string)($automationResult['error'] ?? '')),
            'current_url' => trim((string)($automationResult['currentUrl'] ?? '')),
        ));
    } else {
        list($ok, $meta) = destacamos_login_session($session, DESTACAMOS_TEST_USER, DESTACAMOS_TEST_PASS);

        $result = array_merge($result, array(
            'ok' => $ok ? true : false,
            'error' => trim((string)($meta['error'] ?? '')),
            'current_url' => trim((string)($meta['current_url'] ?? ($meta['login_url'] ?? ''))),
            'login_url' => trim((string)($meta['login_url'] ?? '')),
        ));
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
} finally {
    destacamos_http_cleanup_session($session);
}
