<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/subirPublicidad/destacamos.php';

// Diagnóstico manual:
// 1. Ajusta usuario, password y listing ID.
// 2. Ejecuta: php tools/test_destacamos_subir_gratis.php
// 3. Revisa el JSON y los snapshots HTML guardados en data/publicista/tmp.
//
// El script:
// - hace login en Destacamos
// - abre browse_listings.php
// - localiza el anuncio concreto por ID
// - decide si "Subir gratis" está disponible de verdad
// - si está libre, ejecuta renewad.php
// - vuelve a leer el listado para verificar el cambio
const DESTACAMOS_FREE_BUMP_TEST_USER = 'tracatrack@gmail.com';
const DESTACAMOS_FREE_BUMP_TEST_PASS = 'vsomnos1Q#';
const DESTACAMOS_FREE_BUMP_TEST_LISTING_ID = '1068189';
const DESTACAMOS_FREE_BUMP_TEST_TIMEOUT_MS = 120000;

function test_destacamos_free_bump_debug_dir() {
    $dir = DATA_PATH . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function test_destacamos_free_bump_save_json($data) {
    $path = test_destacamos_free_bump_debug_dir() . '/destacamos_free_bump_test_' . date('Ymd_His') . '_' . substr(sha1(uniqid('', true)), 0, 8) . '.json';
    @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $path;
}

function test_destacamos_free_bump_parse_opacity($style) {
    $style = trim((string)$style);
    if ($style === '') {
        return null;
    }
    if (preg_match('/opacity\s*:\s*([0-9]+(?:\.[0-9]+)?)/i', $style, $match)) {
        return (float)$match[1];
    }
    return null;
}

function test_destacamos_free_bump_is_disabled_link($href, $style) {
    $href = trim((string)$href);
    $style = trim((string)$style);
    if ($href === '' || stripos($href, 'javascript:') === 0) {
        return true;
    }
    $opacity = test_destacamos_free_bump_parse_opacity($style);
    if ($opacity !== null && $opacity <= 0.5) {
        return true;
    }
    return false;
}

function test_destacamos_free_bump_extract_listing_state($html, $baseUrl, $listingId, $snapshotPrefix = '') {
    $listingId = preg_replace('/\D+/', '', (string)$listingId);
    $result = array(
        'found' => false,
        'listing_id' => $listingId,
        'title' => '',
        'phone' => '',
        'detail_url' => '',
        'renew_url' => '',
        'renew_link_text' => '',
        'renew_link_style' => '',
        'renew_link_opacity' => null,
        'free_bump_available' => false,
        'disabled_reason' => '',
        'row_html_path' => '',
    );

    list($dom, $xpath) = destacamos_html_load_xpath((string)$html);
    if (!$dom || !$xpath || $listingId === '') {
        $result['disabled_reason'] = 'No se pudo cargar el HTML del listado.';
        return $result;
    }

    $rowNodes = $xpath->query('//tr[@id="p' . $listingId . '"]');
    if (!$rowNodes || $rowNodes->length === 0 || !($rowNodes->item(0) instanceof DOMElement)) {
        $result['disabled_reason'] = 'No se encontró la fila del anuncio en browse_listings.php.';
        return $result;
    }

    $row = $rowNodes->item(0);
    $result['found'] = true;

    $titleNode = $xpath->query('.//h3//a[1]', $row);
    if ($titleNode && $titleNode->length > 0 && $titleNode->item(0) instanceof DOMElement) {
        $result['title'] = destacamos_html_normalize($titleNode->item(0)->textContent);
        $result['detail_url'] = destacamos_http_build_url($baseUrl, destacamos_node_attr($titleNode->item(0), 'href'));
    }

    $strongs = $xpath->query('.//table[contains(@class, "tabla-detalles-anuncios")]//strong', $row);
    if ($strongs) {
        foreach ($strongs as $strong) {
            $text = destacamos_html_normalize($strong->textContent);
            $digits = destacamos_phone_digits($text);
            if ($digits !== '' && strlen($digits) >= 9) {
                $result['phone'] = $digits;
                break;
            }
        }
    }

    $linkNodes = $xpath->query('.//span[' . destacamos_xpath_has_class('accion-subir-gratis') . ']//a[contains(@href, "renewad.php")]', $row);
    if (!$linkNodes || $linkNodes->length === 0 || !($linkNodes->item(0) instanceof DOMElement)) {
        $result['disabled_reason'] = 'No se encontró enlace renewad.php en la acción de subir gratis.';
    } else {
        $link = $linkNodes->item(0);
        $spanNode = $link->parentNode instanceof DOMElement ? $link->parentNode : null;
        $href = trim((string)destacamos_node_attr($link, 'href'));
        $linkStyle = trim((string)destacamos_node_attr($link, 'style'));
        $spanStyle = $spanNode ? trim((string)destacamos_node_attr($spanNode, 'style')) : '';
        $combinedStyle = trim($linkStyle . ' ' . $spanStyle);
        $opacity = test_destacamos_free_bump_parse_opacity($combinedStyle);

        $result['renew_url'] = $href !== '' ? destacamos_http_build_url($baseUrl, $href) : '';
        $result['renew_link_text'] = destacamos_html_normalize($link->textContent);
        $result['renew_link_style'] = $combinedStyle;
        $result['renew_link_opacity'] = $opacity;
        $result['free_bump_available'] = !test_destacamos_free_bump_is_disabled_link($href, $combinedStyle);

        if (!$result['free_bump_available']) {
            if ($href === '') {
                $result['disabled_reason'] = 'El enlace de subir gratis no tiene href.';
            } elseif ($opacity !== null && $opacity <= 0.5) {
                $result['disabled_reason'] = 'El botón existe pero está desactivado visualmente (opacity=' . $opacity . ').';
            } else {
                $result['disabled_reason'] = 'El enlace de subir gratis no parece ejecutable.';
            }
        }
    }

    $rowHtml = $dom->saveHTML($row);
    if ($rowHtml !== false && trim((string)$rowHtml) !== '') {
        $prefix = trim((string)$snapshotPrefix) !== '' ? trim((string)$snapshotPrefix) : 'destacamos_listing_row';
        $result['row_html_path'] = destacamos_debug_write_snapshot($prefix . '_listing_' . $listingId, $rowHtml, 'html');
    }

    return $result;
}

function test_destacamos_free_bump_run() {
    $username = trim((string)DESTACAMOS_FREE_BUMP_TEST_USER);
    $password = trim((string)DESTACAMOS_FREE_BUMP_TEST_PASS);
    $listingId = trim((string)DESTACAMOS_FREE_BUMP_TEST_LISTING_ID);
    $timeoutSec = (int)ceil(max(30000, (int)DESTACAMOS_FREE_BUMP_TEST_TIMEOUT_MS) / 1000);

    $debugLog = array(
        'enabled' => true,
        'steps' => array(),
    );

    $result = array(
        'ok' => false,
        'tested_at' => now_datetime(),
        'user' => $username,
        'listing_id' => $listingId,
        'login_ok' => false,
        'listing_found_before' => false,
        'free_bump_available_before' => false,
        'renew_attempted' => false,
        'renew_ok' => false,
        'renew_url' => '',
        'current_url' => '',
        'error' => '',
        'before' => array(),
        'after' => array(),
        'confirmations' => array(),
        'debug_log' => array(),
        'saved_json_path' => '',
    );

    if ($username === '' || $password === '' || $listingId === '') {
        $result['error'] = 'Debes definir usuario, password y listing ID en las constantes del script.';
        $result['saved_json_path'] = test_destacamos_free_bump_save_json($result);
        return $result;
    }

    $session = destacamos_http_session($timeoutSec);

    try {
        list($okLogin, $loginMeta) = destacamos_login_session($session, $username, $password, $debugLog);
        $result['login_ok'] = $okLogin ? true : false;
        $result['current_url'] = trim((string)($loginMeta['login_url'] ?? $loginMeta['current_url'] ?? ''));
        if (!$okLogin) {
            $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado.'));
            return $result;
        }

        $listUrl = 'https://www.destacamos.net/browse_listings.php';
        $listBeforeResp = destacamos_http_request($session, 'GET', $listUrl, array('timeout' => $timeoutSec));
        destacamos_debug_record_response($debugLog, 'browse_listings_before', $listBeforeResp);
        if (!$listBeforeResp['ok']) {
            throw new RuntimeException('No se pudo abrir browse_listings.php antes de renovar.');
        }

        $result['current_url'] = trim((string)($listBeforeResp['effective_url'] ?? ''));
        $before = test_destacamos_free_bump_extract_listing_state($listBeforeResp['body'], $listBeforeResp['effective_url'], $listingId, 'destacamos_before');
        $result['before'] = $before;
        $result['listing_found_before'] = !empty($before['found']);
        $result['free_bump_available_before'] = !empty($before['free_bump_available']);
        $result['renew_url'] = trim((string)($before['renew_url'] ?? ''));

        if (empty($before['found'])) {
            $result['error'] = 'No se encontró el anuncio ' . $listingId . ' en browse_listings.php.';
            return $result;
        }

        if (empty($before['free_bump_available'])) {
            $result['error'] = trim((string)($before['disabled_reason'] ?? 'El anuncio no tiene la subida gratis disponible ahora mismo.'));
            return $result;
        }

        $renewUrl = trim((string)($before['renew_url'] ?? ''));
        if ($renewUrl === '') {
            $result['error'] = 'El anuncio aparece disponible pero no se pudo extraer la URL de renew.';
            return $result;
        }

        $result['renew_attempted'] = true;
        $renewResp = destacamos_http_request($session, 'GET', $renewUrl, array(
            'referer' => $listBeforeResp['effective_url'],
            'timeout' => $timeoutSec,
        ));
        destacamos_debug_record_response($debugLog, 'renew_listing', $renewResp);
        if (!$renewResp['ok']) {
            throw new RuntimeException('No se pudo ejecutar renewad.php para el anuncio.');
        }

        $result['current_url'] = trim((string)($renewResp['effective_url'] ?? ''));

        $listAfterResp = destacamos_http_request($session, 'GET', $listUrl, array(
            'referer' => $renewResp['effective_url'],
            'timeout' => $timeoutSec,
        ));
        destacamos_debug_record_response($debugLog, 'browse_listings_after', $listAfterResp);
        if (!$listAfterResp['ok']) {
            throw new RuntimeException('No se pudo reabrir browse_listings.php después de renovar.');
        }

        $after = test_destacamos_free_bump_extract_listing_state($listAfterResp['body'], $listAfterResp['effective_url'], $listingId, 'destacamos_after');
        $result['after'] = $after;

        $confirmations = array();
        if (destacamos_response_has_success_signal((string)($renewResp['body'] ?? ''))) {
            $confirmations[] = 'La respuesta de renew contiene señales textuales de éxito.';
        }
        if (trim((string)($renewResp['effective_url'] ?? '')) !== '' && trim((string)($renewResp['effective_url'] ?? '')) !== $renewUrl) {
            $confirmations[] = 'La URL efectiva cambió tras ejecutar renew.';
        }
        if (!empty($after['found']) && empty($after['free_bump_available'])) {
            $confirmations[] = 'Tras el renew, el botón ya no aparece libre para subir.';
        }

        $result['confirmations'] = $confirmations;
        $result['renew_ok'] = !empty($confirmations);
        $result['ok'] = !empty($confirmations);

        if (empty($confirmations)) {
            $result['error'] = 'La web respondió, pero no quedó ninguna evidencia clara de que la subida gratis se consumiera.';
        }

        return $result;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        return $result;
    } finally {
        $result['debug_log'] = $debugLog;
        $result['saved_json_path'] = test_destacamos_free_bump_save_json($result);
        destacamos_http_cleanup_session($session);
    }
}

if (basename((string)(__FILE__)) === basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    $result = test_destacamos_free_bump_run();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}
