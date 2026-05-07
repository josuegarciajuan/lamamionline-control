<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';

// Diagnóstico manual:
// 1. Rellena usuario/password.
// 2. Elige modo:
//    - 'login' para probar solo acceso
//    - 'listing_update' para probar login + edición genérica + detección de fotos
// 3. Si usas 'listing_update', rellena listingId, fields y photos.
// 4. Ejecuta: php tools/test_mundosexanuncio.php
// 5. Pásame el JSON resultante si hay que ajustar el parser.
const MUNDOSEX_TEST_USER = 'tracatrack@gmail.com';
const MUNDOSEX_TEST_PASS = 'FZ3M7';
const MUNDOSEX_TEST_MODE = 'listing_update'; // 'login' | 'listing_update'
const MUNDOSEX_TEST_LISTING_ID = '99530296';

function test_mundosex_fields() {
    return array(
        // Puedes usar los nombres reales del formulario o estos alias:
        //'title' => 'VALENTINA1 PELIRROJA COLOMBIANA 🔥💦 NINFÓMANA 24H -6313',
        // 'description' => 'Descripcion de prueba',
        // 'provincia' => 'Madrid',   // tambien admite ID: 28
        // 'ciudad' => 'Madrid',      // tambien admite ID: 4362
        // 'zone' => 'LEGAZPI',
        // 'email' => 'tracatrack@gmail.com',
        // 'phone' => '631349504',
        // 'has_whatsapp' => true,
        // 'website' => '',
        // 'twitter_handle' => '',
        // 'professional_editor' => false,
        // 'tag_add' => '',
        // 'protect_photos' => false,
        // 'accept_conditions' => true,
        //
        // Nombres reales del formulario por si prefieres usarlos directos:
         'titol' => 'Valentina reyna del griego',
        // 'zona' => '',
        // 'descripcio' => '',
        // 'id_provincia' => '',
        // 'id_ciudad' => '',
        // 'mail' => '',
        // 'telefono' => '',
        // 'whatsapp' => true,
        // 'enlace' => '',
        // 'twitter' => '',
        // 'editor' => false,
        // 'tag_add' => '',
        // 'marca' => false,
        // 'condiciones' => true,
    );
}

function test_mundosex_photos() {
    return array(
        // Igual que en el script de Destacamos: una ruta absoluta por foto.
        //'/home/oficina/Descargas/check.png',
        // '/ruta/absoluta/a/foto2.jpg',
    );
}

function mundosex_http_user_agent() {
    return 'Mozilla/5.0 (compatible; AtupuertaCRM/1.0; +https://www.mundosexanuncio.com)';
}

function mundosex_http_cookie_file() {
    $dir = rtrim(DATA_PATH, '/') . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/mundosex_' . generate_id('sess') . '.cookie';
}

function mundosex_http_session($timeoutSec = 90) {
    return array(
        'cookie_file' => mundosex_http_cookie_file(),
        'timeout' => max(15, (int)$timeoutSec),
    );
}

function mundosex_http_cleanup_session($session) {
    $cookieFile = trim((string)($session['cookie_file'] ?? ''));
    if ($cookieFile !== '' && is_file($cookieFile)) {
        @unlink($cookieFile);
    }
}

function mundosex_http_build_url($baseUrl, $relativeUrl) {
    $relativeUrl = trim((string)$relativeUrl);
    if ($relativeUrl === '') return $baseUrl;
    if (preg_match('~^https?://~i', $relativeUrl)) return $relativeUrl;
    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) return $relativeUrl;
    if (strpos($relativeUrl, '//') === 0) {
        return $base['scheme'] . ':' . $relativeUrl;
    }
    $root = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
    if (strpos($relativeUrl, '/') === 0) {
        return $root . $relativeUrl;
    }
    $path = isset($base['path']) ? $base['path'] : '/';
    $dir = preg_replace('~/[^/]*$~', '/', $path);
    $combined = $dir . $relativeUrl;
    $parts = explode('/', $combined);
    $out = array();
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($out);
            continue;
        }
        $out[] = $part;
    }
    return $root . '/' . implode('/', $out);
}

function mundosex_http_request($session, $method, $url, $options = array()) {
    if (!function_exists('curl_init')) {
        return array(
            'ok' => false,
            'http_code' => 0,
            'body' => '',
            'headers_raw' => '',
            'effective_url' => $url,
            'error' => 'curl_init no disponible',
            'content_type' => '',
        );
    }

    $method = strtoupper(trim((string)$method));
    $data = $options['data'] ?? null;
    $multipart = !empty($options['multipart']);
    $hasRawBody = array_key_exists('body', $options);
    $headers = is_array($options['headers'] ?? null) ? $options['headers'] : array();
    $timeout = max(15, (int)($options['timeout'] ?? ($session['timeout'] ?? 90)));
    $referer = trim((string)($options['referer'] ?? ''));

    if ($method === 'GET' && is_array($data) && !empty($data)) {
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $url .= $sep . http_build_query($data);
        $data = null;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(20, $timeout));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_USERAGENT, mundosex_http_user_agent());
    curl_setopt($ch, CURLOPT_COOKIEJAR, $session['cookie_file']);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $session['cookie_file']);
    if ($referer !== '') {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($hasRawBody) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$options['body']);
        } elseif ($multipart) {
            $multipartData = is_array($data) ? mundosex_http_flatten_postfields($data) : array();
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipartData);
        } else {
            $payload = is_array($data) ? http_build_query($data) : (string)$data;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($raw === false) {
        $raw = '';
    }

    $headerSize = (int)($info['header_size'] ?? 0);
    $headersRaw = substr((string)$raw, 0, $headerSize);
    $body = substr((string)$raw, $headerSize);

    return array(
        'ok' => ($error === '' && (int)($info['http_code'] ?? 0) < 400),
        'http_code' => (int)($info['http_code'] ?? 0),
        'body' => (string)$body,
        'headers_raw' => (string)$headersRaw,
        'effective_url' => (string)($info['url'] ?? $url),
        'error' => (string)$error,
        'content_type' => (string)($info['content_type'] ?? ''),
    );
}

function mundosex_http_flatten_postfields($data, $prefix = null) {
    $flat = array();
    foreach ((array)$data as $key => $value) {
        $key = (string)$key;
        $fieldKey = $prefix === null ? $key : ($prefix . '[' . $key . ']');
        if ($value instanceof CURLFile) {
            $flat[$fieldKey] = $value;
            continue;
        }
        if (is_array($value)) {
            if (substr($fieldKey, -2) === '[]') {
                $base = substr($fieldKey, 0, -2);
                foreach (array_values($value) as $idx => $child) {
                    if (is_array($child)) {
                        $flat = array_merge($flat, mundosex_http_flatten_postfields($child, $base . '[' . $idx . ']'));
                    } else {
                        $flat[$base . '[' . $idx . ']'] = $child;
                    }
                }
                continue;
            }
            $flat = array_merge($flat, mundosex_http_flatten_postfields($value, $fieldKey));
            continue;
        }
        $flat[$fieldKey] = $value;
    }
    return $flat;
}

function mundosex_html_normalize($text) {
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function mundosex_strtolower($text) {
    $text = (string)$text;
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function mundosex_strpos($haystack, $needle) {
    return function_exists('mb_strpos') ? mb_strpos((string)$haystack, (string)$needle, 0, 'UTF-8') : strpos((string)$haystack, (string)$needle);
}

function mundosex_html_load_xpath($html) {
    if (!class_exists('DOMDocument')) return array(null, null);
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . (string)$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return array($dom, new DOMXPath($dom));
}

function mundosex_node_attr($node, $name) {
    return ($node instanceof DOMElement && $node->hasAttribute($name)) ? (string)$node->getAttribute($name) : '';
}

function mundosex_form_data_assign(&$data, $name, $value, $append = false) {
    if ($name === '') return;
    $isArrayName = substr($name, -2) === '[]';
    if ($append || $isArrayName) {
        if (!isset($data[$name]) || !is_array($data[$name])) {
            $data[$name] = array();
        }
        $data[$name][] = $value;
        return;
    }
    $data[$name] = $value;
}

function mundosex_form_build($formNode, $baseUrl) {
    $form = array(
        'action_url' => mundosex_http_build_url($baseUrl, mundosex_node_attr($formNode, 'action')),
        'method' => strtoupper(trim((string)(mundosex_node_attr($formNode, 'method') ?: 'POST'))),
        'id' => mundosex_node_attr($formNode, 'id'),
        'fields' => array(),
        'submitters' => array(),
        'data' => array(),
    );

    foreach ($formNode->getElementsByTagName('input') as $input) {
        $type = strtolower(trim((string)(mundosex_node_attr($input, 'type') ?: 'text')));
        $name = trim((string)mundosex_node_attr($input, 'name'));
        $id = trim((string)mundosex_node_attr($input, 'id'));
        $value = (string)mundosex_node_attr($input, 'value');
        if ($input->hasAttribute('disabled')) continue;

        if (in_array($type, array('submit', 'button', 'image'), true)) {
            $form['submitters'][] = array('name' => $name, 'value' => $value, 'id' => $id, 'type' => $type);
            continue;
        }
        $field = array(
            'tag' => 'input',
            'type' => $type,
            'name' => $name,
            'id' => $id,
            'value' => $value,
            'checked' => $input->hasAttribute('checked'),
            'multiple' => $input->hasAttribute('multiple'),
        );
        $form['fields'][] = $field;

        if ($type === 'file') {
            continue;
        }
        if ($name === '') continue;
        if (in_array($type, array('checkbox', 'radio'), true)) {
            if ($input->hasAttribute('checked')) {
                mundosex_form_data_assign($form['data'], $name, $value !== '' ? $value : 'on', true);
            }
            continue;
        }
        mundosex_form_data_assign($form['data'], $name, $value);
    }

    foreach ($formNode->getElementsByTagName('textarea') as $textarea) {
        $name = trim((string)mundosex_node_attr($textarea, 'name'));
        $id = trim((string)mundosex_node_attr($textarea, 'id'));
        if ($textarea->hasAttribute('disabled')) continue;
        $value = (string)$textarea->textContent;
        $form['fields'][] = array('tag' => 'textarea', 'type' => 'textarea', 'name' => $name, 'id' => $id, 'value' => $value);
        if ($name !== '') {
            mundosex_form_data_assign($form['data'], $name, $value);
        }
    }

    foreach ($formNode->getElementsByTagName('select') as $select) {
        $name = trim((string)mundosex_node_attr($select, 'name'));
        $id = trim((string)mundosex_node_attr($select, 'id'));
        if ($select->hasAttribute('disabled')) continue;
        $multiple = $select->hasAttribute('multiple');
        $options = array();
        $selectedValues = array();
        foreach ($select->getElementsByTagName('option') as $option) {
            $optionValue = (string)mundosex_node_attr($option, 'value');
            $label = mundosex_html_normalize($option->textContent);
            $selected = $option->hasAttribute('selected');
            $options[] = array('value' => $optionValue, 'label' => $label, 'selected' => $selected);
            if ($selected) {
                $selectedValues[] = $optionValue;
            }
        }
        if (empty($selectedValues) && !$multiple && !empty($options)) {
            $selectedValues[] = (string)($options[0]['value'] ?? '');
        }
        $field = array(
            'tag' => 'select',
            'type' => 'select',
            'name' => $name,
            'id' => $id,
            'value' => $multiple ? $selectedValues : (string)($selectedValues[0] ?? ''),
            'multiple' => $multiple,
            'options' => $options,
        );
        $form['fields'][] = $field;
        if ($name !== '') {
            mundosex_form_data_assign($form['data'], $name, $field['value'], $multiple);
        }
    }

    foreach ($formNode->getElementsByTagName('button') as $button) {
        $type = strtolower(trim((string)(mundosex_node_attr($button, 'type') ?: 'submit')));
        if (!in_array($type, array('submit', 'button'), true)) continue;
        $form['submitters'][] = array(
            'name' => trim((string)mundosex_node_attr($button, 'name')),
            'value' => (string)(mundosex_node_attr($button, 'value') ?: mundosex_html_normalize($button->textContent)),
            'id' => trim((string)mundosex_node_attr($button, 'id')),
            'type' => $type,
        );
    }

    return $form;
}

function mundosex_extract_form($html, $baseUrl, $criteria = array()) {
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if (!$dom || !$xpath) return null;
    $forms = $xpath->query('//form');
    if (!$forms) return null;

    $inputNames = array_map('strval', (array)($criteria['input_names'] ?? array()));
    $inputIds = array_map('strval', (array)($criteria['input_ids'] ?? array()));
    $formId = trim((string)($criteria['form_id'] ?? ''));
    $actionContains = trim((string)($criteria['action_contains'] ?? ''));

    foreach ($forms as $formNode) {
        if (!($formNode instanceof DOMElement)) continue;
        if ($formId !== '' && mundosex_node_attr($formNode, 'id') === $formId) {
            return mundosex_form_build($formNode, $baseUrl);
        }

        $matches = false;
        if ($actionContains !== '') {
            $action = mundosex_node_attr($formNode, 'action');
            if ($action !== '' && mundosex_strpos(mundosex_strtolower($action), mundosex_strtolower($actionContains)) !== false) {
                $matches = true;
            }
        }
        if (!$matches && !empty($inputNames)) {
            foreach ($inputNames as $name) {
                $query = './/*[@name="' . addslashes($name) . '"]';
                if ($xpath->query($query, $formNode)->length > 0) {
                    $matches = true;
                    break;
                }
            }
        }
        if (!$matches && !empty($inputIds)) {
            foreach ($inputIds as $id) {
                $query = './/*[@id="' . addslashes($id) . '"]';
                if ($xpath->query($query, $formNode)->length > 0) {
                    $matches = true;
                    break;
                }
            }
        }
        if ($matches) {
            return mundosex_form_build($formNode, $baseUrl);
        }
    }

    if ($forms->length > 0 && $forms->item(0) instanceof DOMElement) {
        return mundosex_form_build($forms->item(0), $baseUrl);
    }
    return null;
}

function mundosex_form_find_field_indexes($form, $fieldKey) {
    $fieldKey = trim((string)$fieldKey);
    $indexes = array();
    foreach ((array)($form['fields'] ?? array()) as $index => $field) {
        $name = trim((string)($field['name'] ?? ''));
        $id = trim((string)($field['id'] ?? ''));
        $normalizedName = preg_replace('/\[\]$/', '', $name);
        if ($fieldKey !== '' && ($fieldKey === $name || $fieldKey === $id || $fieldKey === $normalizedName)) {
            $indexes[] = $index;
        }
    }
    return $indexes;
}

function mundosex_form_normalize_choice($value) {
    return mundosex_strtolower(mundosex_html_normalize((string)$value));
}

function mundosex_form_normalize_choice_list($values) {
    $normalized = array();
    foreach ((array)$values as $value) {
        $token = mundosex_form_normalize_choice($value);
        if ($token === '') continue;
        $normalized[$token] = $token;
    }
    return array_values($normalized);
}

function mundosex_form_choice_matches($candidate, $desiredValues) {
    $candidate = mundosex_form_normalize_choice($candidate);
    if ($candidate === '') return false;
    foreach (mundosex_form_normalize_choice_list($desiredValues) as $desired) {
        if ($candidate === $desired) {
            return true;
        }
    }
    return false;
}

function mundosex_form_set_value(&$form, $fieldKey, $value) {
    $indexes = mundosex_form_find_field_indexes($form, $fieldKey);
    if (empty($indexes)) {
        $form['data'][$fieldKey] = $value;
        return false;
    }

    $valueIsArray = is_array($value);
    $firstIndex = $indexes[0];

    foreach ($indexes as $index) {
        $field = $form['fields'][$index];
        $name = trim((string)($field['name'] ?? ''));
        $type = strtolower(trim((string)($field['type'] ?? 'text')));
        $tag = strtolower(trim((string)($field['tag'] ?? 'input')));
        $form['fields'][$index]['value'] = $value;

        if ($name === '') continue;

        if ($tag === 'select' || $type === 'select') {
            $selectedValues = array();
            $targets = $valueIsArray ? $value : array($value);
            foreach ((array)($field['options'] ?? array()) as $option) {
                $optionValue = (string)($option['value'] ?? '');
                $optionLabel = (string)($option['label'] ?? '');
                if (mundosex_form_choice_matches($optionValue, $targets) || mundosex_form_choice_matches($optionLabel, $targets)) {
                    $selectedValues[] = $optionValue;
                    if (empty($field['multiple'])) {
                        break;
                    }
                }
            }
            if (empty($field['multiple'])) {
                $selectedValue = (string)($selectedValues[0] ?? '');
                if ($selectedValue === '' && !empty($field['options'][0]['value'])) {
                    $selectedValue = (string)$field['options'][0]['value'];
                }
                $form['data'][$name] = $selectedValue;
                $form['fields'][$index]['value'] = $selectedValue;
                continue;
            }
            if (empty($selectedValues)) {
                unset($form['data'][$name]);
                $form['fields'][$index]['value'] = array();
                continue;
            }
            $form['data'][$name] = array_values($selectedValues);
            $form['fields'][$index]['value'] = array_values($selectedValues);
            continue;
        }

        if (in_array($type, array('checkbox', 'radio'), true)) {
            $fieldValue = trim((string)($field['value'] ?? '')) !== '' ? (string)$field['value'] : 'on';
            $hasMultipleChoices = count($indexes) > 1 || substr($name, -2) === '[]';
            if ($valueIsArray) {
                if ($index === $firstIndex) {
                    unset($form['data'][$name]);
                }
                $shouldCheck = mundosex_form_choice_matches($fieldValue, $value);
                $form['fields'][$index]['checked'] = $shouldCheck;
                if ($shouldCheck) {
                    mundosex_form_data_assign($form['data'], $name, $fieldValue, true);
                }
                continue;
            }

            $truthy = is_bool($value) ? $value : in_array(mundosex_form_normalize_choice($value), array('1', 'true', 'si', 'sí', 'yes', 'on', mundosex_form_normalize_choice($fieldValue)), true);
            if ($hasMultipleChoices && !is_bool($value)) {
                if ($index === $firstIndex) {
                    unset($form['data'][$name]);
                }
                $truthy = mundosex_form_choice_matches($fieldValue, array($value));
            }

            if ($truthy) {
                $form['fields'][$index]['checked'] = true;
                if ($hasMultipleChoices) {
                    mundosex_form_data_assign($form['data'], $name, $fieldValue, true);
                } else {
                    $form['data'][$name] = $fieldValue;
                }
            } else {
                $form['fields'][$index]['checked'] = false;
                if (!$hasMultipleChoices) {
                    unset($form['data'][$name]);
                }
            }
            continue;
        }

        $form['data'][$name] = (string)$value;
    }

    return true;
}

function mundosex_prepare_fields_payload($fields) {
    $mapped = array();
    $aliases = array(
        'title' => 'titol',
        'titulo' => 'titol',
        'description' => 'descripcio',
        'descripcion' => 'descripcio',
        'province' => 'id_provincia',
        'provincia' => 'id_provincia',
        'city' => 'id_ciudad',
        'ciudad' => 'id_ciudad',
        'zone' => 'zona',
        'barrio' => 'zona',
        'email' => 'mail',
        'phone' => 'telefono',
        'telefono' => 'telefono',
        'has_whatsapp' => 'whatsapp',
        'website' => 'enlace',
        'twitter_handle' => 'twitter',
        'professional_editor' => 'editor',
        'protect_photos' => 'marca',
        'accept_conditions' => 'condiciones',
    );
    foreach ((array)$fields as $key => $value) {
        $normalizedKey = trim((string)$key);
        if ($normalizedKey === '') continue;
        $targetKey = $aliases[$normalizedKey] ?? $normalizedKey;
        $mapped[$targetKey] = $value;
    }
    return $mapped;
}

function mundosex_form_pick_submit(&$form, $preferredIds = array(), $preferredKeywords = array()) {
    $submitters = (array)($form['submitters'] ?? array());
    if (empty($submitters)) return;
    foreach ($preferredIds as $preferredId) {
        foreach ($submitters as $submitter) {
            if (($submitter['id'] ?? '') === $preferredId) {
                if (($submitter['name'] ?? '') !== '') {
                    $form['data'][$submitter['name']] = (string)($submitter['value'] ?? '');
                }
                return;
            }
        }
    }
    foreach ($preferredKeywords as $keyword) {
        foreach ($submitters as $submitter) {
            $haystack = mundosex_strtolower(mundosex_html_normalize(($submitter['id'] ?? '') . ' ' . ($submitter['name'] ?? '') . ' ' . ($submitter['value'] ?? '')));
            if ($keyword !== '' && mundosex_strpos($haystack, mundosex_strtolower($keyword)) !== false) {
                if (($submitter['name'] ?? '') !== '') {
                    $form['data'][$submitter['name']] = (string)($submitter['value'] ?? '');
                }
                return;
            }
        }
    }
    $first = $submitters[0];
    if (($first['name'] ?? '') !== '') {
        $form['data'][$first['name']] = (string)($first['value'] ?? '');
    }
}

function mundosex_form_read_value($form, $fieldKey) {
    $indexes = mundosex_form_find_field_indexes($form, $fieldKey);
    if (empty($indexes)) {
        return array(false, null);
    }

    $field = $form['fields'][$indexes[0]];
    $name = trim((string)($field['name'] ?? ''));
    $type = strtolower(trim((string)($field['type'] ?? 'text')));
    $tag = strtolower(trim((string)($field['tag'] ?? 'input')));
    $data = $name !== '' ? ($form['data'][$name] ?? null) : null;

    if ($tag === 'select' || $type === 'select') {
        return array(true, $data !== null ? $data : ($field['value'] ?? ''));
    }

    if (in_array($type, array('checkbox', 'radio'), true)) {
        $isMultiple = substr($name, -2) === '[]' || count($indexes) > 1;
        if ($isMultiple) {
            if ($data === null) {
                return array(true, array());
            }
            return array(true, is_array($data) ? array_values($data) : array($data));
        }
        return array(true, $data !== null);
    }

    return array(true, $field['value'] ?? ($data ?? ''));
}

function mundosex_compare_values($current, $expected) {
    if (is_bool($expected)) {
        return (bool)$current === $expected;
    }
    if (is_array($expected)) {
        $currentList = is_array($current) ? $current : array($current);
        $a = mundosex_form_normalize_choice_list($currentList);
        $b = mundosex_form_normalize_choice_list($expected);
        sort($a);
        sort($b);
        return $a === $b;
    }
    return mundosex_strtolower(mundosex_html_normalize((string)$current)) === mundosex_strtolower(mundosex_html_normalize((string)$expected));
}

function mundosex_page_has_login_form($html) {
    $html = (string)$html;
    if (preg_match('/<form[^>]+id=["\']usuarioLogin["\']/i', $html)) {
        return true;
    }
    return preg_match('/name=["\']email["\'][^>]*id=["\']email["\']|id=["\']email["\'][^>]*name=["\']email["\']/i', $html) === 1
        && preg_match('/name=["\']password["\'][^>]*id=["\']password["\']|id=["\']password["\'][^>]*name=["\']password["\']/i', $html) === 1;
}

function mundosex_page_has_legal_gate($html) {
    return preg_match('/id=["\']alegal["\']/i', (string)$html) === 1;
}

function mundosex_page_has_logged_area($html) {
    $html = (string)$html;
    if (mundosex_page_has_login_form($html)) {
        return false;
    }
    if (preg_match('~/privado/logout/|/cerrarSesion|/misAnuncios~i', $html)) {
        return true;
    }
    $signals = array('saldo', 'renovar', 'editar anuncio', 'mis anuncios', 'cerrar sesión', 'cerrar sesion', 'salir');
    $haystack = mundosex_strtolower(mundosex_html_normalize($html));
    foreach ($signals as $signal) {
        if (mundosex_strpos($haystack, mundosex_strtolower($signal)) !== false) {
            return true;
        }
    }
    return false;
}

function mundosex_extract_login_error_message($html) {
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if ($dom && $xpath) {
        foreach (array(
            '//*[@id="men"]',
            '//*[contains(@class, "men")]',
            '//*[contains(@class, "error")]',
            '//*[contains(@class, "warning")]',
            '//*[contains(@class, "alert")]',
            '//*[contains(@id, "error")]',
        ) as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes) continue;
            foreach ($nodes as $node) {
                $text = mundosex_html_normalize($node->textContent ?? '');
                $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                if ($text !== '' && $len >= 6) {
                    return $text;
                }
            }
        }
    }

    if (preg_match('/(email o la contraseña[^<\n\r]{0,180}|contraseña[^<\n\r]{0,180}inválid[^<\n\r]{0,180}|cuenta[^<\n\r]{0,180}bloquead[^<\n\r]{0,180}|demasiados intentos[^<\n\r]{0,180})/iu', (string)$html, $match)) {
        return mundosex_html_normalize($match[1]);
    }
    return '';
}

function mundosex_debug_dir() {
    $dir = DATA_PATH . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function mundosex_build_forms_debug($html, $baseUrl) {
    $debugForms = array();
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if (!$dom || !$xpath) {
        return $debugForms;
    }
    $forms = $xpath->query('//form');
    if (!$forms) {
        return $debugForms;
    }
    foreach ($forms as $index => $formNode) {
        if (!($formNode instanceof DOMElement)) continue;
        $form = mundosex_form_build($formNode, $baseUrl);
        $fieldNames = array();
        $fileInputs = array();
        foreach ((array)$form['fields'] as $field) {
            $label = trim((string)($field['name'] ?? ($field['id'] ?? '')));
            if ($label !== '') {
                $fieldNames[] = $label;
            }
            if (($field['type'] ?? '') === 'file') {
                $fileInputs[] = $label;
            }
        }
        $debugForms[] = array(
            'index' => $index,
            'id' => $form['id'] ?? '',
            'action_url' => $form['action_url'] ?? '',
            'method' => $form['method'] ?? '',
            'field_names' => array_values(array_unique($fieldNames)),
            'file_inputs' => array_values(array_unique($fileInputs)),
        );
    }
    return $debugForms;
}

function mundosex_extract_editable_fields($form) {
    $items = array();
    foreach ((array)($form['fields'] ?? array()) as $field) {
        $name = trim((string)($field['name'] ?? ''));
        $id = trim((string)($field['id'] ?? ''));
        $key = $name !== '' ? $name : $id;
        if ($key === '') continue;

        $type = strtolower(trim((string)($field['type'] ?? 'text')));
        $tag = strtolower(trim((string)($field['tag'] ?? 'input')));
        if (in_array($type, array('hidden', 'submit', 'button', 'image', 'file'), true)) {
            continue;
        }

        $normalizedKey = preg_replace('/\[\]$/', '', $key);
        if (isset($items[$normalizedKey])) {
            if ($type === 'checkbox' || $type === 'radio' || !empty($field['multiple'])) {
                $items[$normalizedKey]['multiple'] = true;
            }
            continue;
        }

        $value = $field['value'] ?? '';
        if (is_array($value)) {
            $value = array_values($value);
        } else {
            $value = (string)$value;
        }

        $item = array(
            'key' => $normalizedKey,
            'name' => $name,
            'id' => $id,
            'tag' => $tag,
            'type' => $type,
            'multiple' => !empty($field['multiple']) || $type === 'checkbox' || $type === 'radio' || substr($name, -2) === '[]',
            'current_value' => $value,
        );
        if ($tag === 'select' || $type === 'select') {
            $options = array();
            foreach ((array)($field['options'] ?? array()) as $option) {
                $options[] = array(
                    'value' => (string)($option['value'] ?? ''),
                    'label' => (string)($option['label'] ?? ''),
                    'selected' => !empty($option['selected']),
                );
            }
            $item['options'] = $options;
        }
        $items[$normalizedKey] = $item;
    }
    return array_values($items);
}

function mundosex_build_fields_template_php($editableFields) {
    $lines = array('return array(');
    foreach ((array)$editableFields as $field) {
        $key = (string)($field['key'] ?? '');
        if ($key === '') continue;
        $multiple = !empty($field['multiple']);
        $sample = $multiple ? 'array()' : "''";
        if (($field['tag'] ?? '') === 'select' || ($field['type'] ?? '') === 'select') {
            $currentLabel = '';
            foreach ((array)($field['options'] ?? array()) as $option) {
                if (!empty($option['selected'])) {
                    $currentLabel = (string)($option['label'] ?? '');
                    break;
                }
            }
            if ($currentLabel !== '') {
                $lines[] = "    // '" . addslashes($key) . "' => '" . addslashes($currentLabel) . "',";
                continue;
            }
        }
        if (($field['type'] ?? '') === 'checkbox') {
            $sample = 'false';
        }
        $lines[] = "    // '" . addslashes($key) . "' => " . $sample . ',';
    }
    $lines[] = ');';
    return implode(PHP_EOL, $lines);
}

function mundosex_extract_select_options_map($editableFields) {
    $map = array();
    foreach ((array)$editableFields as $field) {
        $key = trim((string)($field['key'] ?? ''));
        if ($key === '' || empty($field['options'])) continue;
        $map[$key] = array();
        foreach ((array)$field['options'] as $option) {
            $value = trim((string)($option['value'] ?? ''));
            if ($value === '') continue;
            $map[$key][] = array(
                'value' => $value,
                'label' => trim((string)($option['label'] ?? '')),
                'selected' => !empty($option['selected']),
            );
        }
    }
    return $map;
}

function mundosex_extract_upload_hints($html, $baseUrl) {
    $hints = array(
        'file_inputs' => array(),
        'delete_field_keys' => array(),
        'upload_related_urls' => array(),
    );
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if ($dom && $xpath) {
        $nodes = $xpath->query('//input[@type="file"]');
        if ($nodes) {
            foreach ($nodes as $node) {
                if (!($node instanceof DOMElement)) continue;
                $hints['file_inputs'][] = array(
                    'name' => mundosex_node_attr($node, 'name'),
                    'id' => mundosex_node_attr($node, 'id'),
                    'multiple' => $node->hasAttribute('multiple'),
                );
            }
        }
        foreach ($xpath->query('//input[@type="checkbox" or @type="hidden" or @type="submit"]') ?: array() as $node) {
            if (!($node instanceof DOMElement)) continue;
            $key = trim((string)(mundosex_node_attr($node, 'name') ?: mundosex_node_attr($node, 'id')));
            $haystack = mundosex_strtolower($key . ' ' . mundosex_node_attr($node, 'value'));
            if ($key !== '' && preg_match('/delete|borrar|eliminar|foto|imagen/', $haystack)) {
                $hints['delete_field_keys'][$key] = $key;
            }
        }
    }
    if (preg_match_all('/(?:https?:\/\/|\/)[^\'"\s<>]+/i', (string)$html, $matches)) {
        foreach ((array)($matches[0] ?? array()) as $url) {
            $resolved = mundosex_http_build_url($baseUrl, $url);
            if (preg_match('/upload|subir|foto|imagen|delete|borrar|eliminar/i', $resolved)) {
                $hints['upload_related_urls'][$resolved] = $resolved;
            }
        }
    }
    $hints['delete_field_keys'] = array_values($hints['delete_field_keys']);
    $hints['upload_related_urls'] = array_values($hints['upload_related_urls']);
    return $hints;
}

function mundosex_extract_selected_tag_ids($html) {
    $tagIds = array();
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if (!$dom || !$xpath) {
        return array();
    }
    $nodes = $xpath->query('//*[@id="tags_c"]/div[@data-id]');
    if (!$nodes) {
        return array();
    }
    foreach ($nodes as $node) {
        if (!($node instanceof DOMElement)) continue;
        $tagId = trim((string)$node->getAttribute('data-id'));
        if ($tagId !== '') {
            $tagIds[$tagId] = $tagId;
        }
    }
    return array_values($tagIds);
}

function mundosex_dump_page_debug($session, $url, $prefix) {
    $resp = mundosex_http_request($session, 'GET', $url, array('timeout' => 90));
    $debug = array(
        'ok' => $resp['ok'] ?? false,
        'http_code' => $resp['http_code'] ?? 0,
        'effective_url' => $resp['effective_url'] ?? '',
        'error' => $resp['error'] ?? '',
        'content_type' => $resp['content_type'] ?? '',
        'body_length' => strlen((string)($resp['body'] ?? '')),
        'has_login_form' => mundosex_page_has_login_form((string)($resp['body'] ?? '')),
        'has_legal_gate' => mundosex_page_has_legal_gate((string)($resp['body'] ?? '')),
        'forms' => mundosex_build_forms_debug((string)($resp['body'] ?? ''), (string)($resp['effective_url'] ?? $url)),
        'upload_hints' => mundosex_extract_upload_hints((string)($resp['body'] ?? ''), (string)($resp['effective_url'] ?? $url)),
        'saved_html_path' => '',
        'saved_json_path' => '',
    );

    $stamp = date('Ymd_His');
    $dir = mundosex_debug_dir();
    $htmlPath = $dir . '/' . $prefix . '_' . $stamp . '.html';
    $jsonPath = $dir . '/' . $prefix . '_' . $stamp . '.json';
    @file_put_contents($htmlPath, (string)($resp['body'] ?? ''));
    @file_put_contents($jsonPath, json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $debug['saved_html_path'] = $htmlPath;
    $debug['saved_json_path'] = $jsonPath;

    return array($resp, $debug);
}

function mundosex_login_session(&$session, $username, $password) {
    $loginUrl = 'https://www.mundosexanuncio.com/misAnuncios';
    $page = mundosex_http_request($session, 'GET', $loginUrl, array(
        'timeout' => 60,
        'headers' => array(
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ),
    ));
    if (!$page['ok']) {
        return array(false, array('error' => 'No se pudo abrir la página de login: ' . ($page['error'] !== '' ? $page['error'] : ('HTTP ' . $page['http_code']))));
    }

    $form = mundosex_extract_form($page['body'], $page['effective_url'], array(
        'form_id' => 'usuarioLogin',
        'input_ids' => array('email', 'password'),
        'input_names' => array('email', 'password'),
        'action_contains' => '/privado/login/',
    ));
    if (!$form) {
        return array(false, array('error' => 'No se encontró el formulario de login de MundosexAnuncio.'));
    }

    mundosex_form_set_value($form, 'email', $username);
    mundosex_form_set_value($form, 'password', $password);
    mundosex_form_pick_submit($form, array(), array('acceder', 'entrar', 'login'));

    $resp = mundosex_http_request($session, $form['method'], $form['action_url'], array(
        'data' => $form['data'],
        'referer' => $page['effective_url'],
        'headers' => array(
            'Origin: https://www.mundosexanuncio.com',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ),
        'timeout' => 60,
    ));
    if (!$resp['ok']) {
        return array(false, array('error' => 'No se pudo enviar el login: ' . ($resp['error'] !== '' ? $resp['error'] : ('HTTP ' . $resp['http_code']))));
    }

    if (mundosex_page_has_login_form($resp['body'])) {
        $errorMessage = mundosex_extract_login_error_message($resp['body']);
        return array(false, array(
            'error' => $errorMessage !== '' ? $errorMessage : 'Login no confirmado. Verifica email y contraseña.',
            'current_url' => $resp['effective_url'],
            'legal_gate_visible' => mundosex_page_has_legal_gate($resp['body']),
        ));
    }

    return array(true, array(
        'login_url' => $resp['effective_url'],
        'legal_gate_visible' => mundosex_page_has_legal_gate($page['body']),
    ));
}

function mundosex_extract_edit_form($html, $baseUrl, $preferredFields = array()) {
    list($dom, $xpath) = mundosex_html_load_xpath($html);
    if (!$dom || !$xpath) return null;
    $forms = $xpath->query('//form');
    if (!$forms) return null;

    $bestForm = null;
    $bestScore = -1;

    foreach ($forms as $formNode) {
        if (!($formNode instanceof DOMElement)) continue;
        $form = mundosex_form_build($formNode, $baseUrl);
        if (($form['id'] ?? '') === 'usuarioLogin') {
            continue;
        }
        $fieldNames = array();
        $editableCount = 0;
        foreach ((array)$form['fields'] as $field) {
            $name = trim((string)($field['name'] ?? ''));
            $id = trim((string)($field['id'] ?? ''));
            $type = strtolower(trim((string)($field['type'] ?? 'text')));
            if (!in_array($type, array('hidden', 'submit', 'button'), true)) {
                $editableCount++;
            }
            if ($name !== '') $fieldNames[] = $name;
            if ($id !== '') $fieldNames[] = $id;
        }
        $score = 0;
        $actionUrl = (string)($form['action_url'] ?? '');
        if ($actionUrl !== '' && preg_match('~/publicar/editar/~i', $actionUrl)) {
            $score += 4;
        }
        if ($editableCount >= 3) {
            $score += 2;
        }
        foreach ((array)$preferredFields as $fieldKey) {
            if (in_array($fieldKey, $fieldNames, true)) {
                $score += 2;
            }
        }
        if (!empty($form['submitters'])) {
            $score += 1;
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestForm = $form;
        }
    }

    return $bestForm;
}

function mundosex_validate_saved_fields($html, $baseUrl, $expected) {
    $form = mundosex_extract_edit_form($html, $baseUrl, array_keys((array)$expected));
    if (!$form) {
        return array(false, array('No se pudo volver a leer el formulario de edición tras guardar.'));
    }

    $mismatches = array();
    foreach ((array)$expected as $fieldKey => $expectedValue) {
        list($found, $currentValue) = mundosex_form_read_value($form, $fieldKey);
        if (!$found) {
            $mismatches[] = 'Campo no encontrado tras guardar: ' . $fieldKey;
            continue;
        }
        if (!mundosex_compare_values($currentValue, $expectedValue)) {
            $mismatches[] = 'Valor no aplicado en ' . $fieldKey;
        }
    }

    return array(empty($mismatches), $mismatches);
}

function mundosex_build_multipart_data($form, $photoPaths) {
    $data = (array)($form['data'] ?? array());
    $slots = array();
    foreach ((array)($form['fields'] ?? array()) as $field) {
        $name = trim((string)($field['name'] ?? ''));
        $type = strtolower(trim((string)($field['type'] ?? '')));
        if ($type === 'file' && preg_match('/^image_(\d+)$/', $name, $match)) {
            $slot = (int)$match[1];
            if (!isset($slots[$slot])) {
                $slots[$slot] = array(
                    'file_field' => $name,
                    'existing_id_field' => 'image[id][' . $slot . ']',
                    'delete_field' => 'image[del][' . $slot . ']',
                    'mod_field' => 'image[mod][' . $slot . ']',
                );
            }
        }
    }
    ksort($slots);

    if (empty($slots)) {
        return array(false, array(), 'No se encontró input file en la página autenticada.');
    }
    if (empty($photoPaths)) {
        $data['condiciones'] = 'on';
        return array(true, $data, '');
    }

    foreach ($photoPaths as $photoPath) {
        if (!is_file($photoPath)) {
            return array(false, array(), 'No existe el archivo de imagen: ' . $photoPath);
        }
    }

    if (count($photoPaths) > count($slots)) {
        return array(false, array(), 'Has pasado más fotos de las que permite el formulario actual (' . count($slots) . ').');
    }

    $slotNumbers = array_values(array_keys($slots));
    $requestedCount = count($photoPaths);
    foreach ($slotNumbers as $position => $slot) {
        $slotMeta = $slots[$slot];
        $hasExisting = !empty($data[$slotMeta['existing_id_field']] ?? null);
        if ($position < $requestedCount) {
            $data[$slotMeta['file_field']] = curl_file_create($photoPaths[$position]);
            if (array_key_exists($slotMeta['delete_field'], $data)) {
                $data[$slotMeta['delete_field']] = '0';
            }
            if (array_key_exists($slotMeta['mod_field'], $data)) {
                $data[$slotMeta['mod_field']] = $hasExisting ? '1' : '0';
            }
        } else {
            if ($hasExisting && array_key_exists($slotMeta['delete_field'], $data)) {
                $data[$slotMeta['delete_field']] = '1';
            }
            if ($hasExisting && array_key_exists($slotMeta['mod_field'], $data)) {
                $data[$slotMeta['mod_field']] = '1';
            }
        }
    }

    $data['condiciones'] = 'on';

    return array(true, $data, '');
}

function mundosex_build_submit_data($form, $photoPaths = array()) {
    list($ok, $data, $error) = mundosex_build_multipart_data($form, $photoPaths);
    if (!$ok) {
        return array(false, array(), $error);
    }
    mundosex_form_pick_submit($form, array(), array('guardar', 'actualizar', 'editar'));
    foreach ((array)$form['submitters'] as $submitter) {
        if (($submitter['name'] ?? '') !== '' && !array_key_exists($submitter['name'], $data)) {
            $data[$submitter['name']] = (string)($submitter['value'] ?? '');
            break;
        }
    }
    return array(true, $data, '');
}

function mundosex_submit_action_url($actionUrl) {
    $actionUrl = trim((string)$actionUrl);
    if ($actionUrl === '') {
        return $actionUrl;
    }
    if (preg_match('~/publicar/insertar/\d+$~', $actionUrl)) {
        return rtrim($actionUrl, '/') . '/m:as';
    }
    return $actionUrl;
}

function mundosex_try_update_listing_photos(&$session, $editPage, $editUrl, $photoPaths) {
    $existingBefore = 0;
    if (preg_match_all('/<span[^>]+class=["\']prev_image["\'][^>]*>\s*<img\b/iu', (string)($editPage['body'] ?? ''), $matches)) {
        $existingBefore = count((array)($matches[0] ?? array()));
    }
    $debug = array(
        'photosPageOk' => true,
        'photosDeleted' => 0,
        'photosUploaded' => 0,
        'warnings' => array(),
        'upload_hints' => mundosex_extract_upload_hints((string)($editPage['body'] ?? ''), (string)($editPage['effective_url'] ?? $editUrl)),
        'requested_photo_paths' => array_values((array)$photoPaths),
        'existing_photo_count_before' => $existingBefore,
    );

    $form = mundosex_extract_edit_form((string)($editPage['body'] ?? ''), (string)($editPage['effective_url'] ?? $editUrl), array());
    if (!$form) {
        return array(false, array_merge($debug, array(
            'error' => 'No se encontró un formulario autenticado desde el que gestionar las fotos.',
        )));
    }
    $debug['detected_photo_form_action'] = (string)($form['action_url'] ?? '');

    list($okMultipart, $multipartData, $multipartError) = mundosex_build_submit_data($form, $photoPaths);
    if (!$okMultipart) {
        return array(false, array_merge($debug, array(
            'error' => $multipartError,
        )));
    }

    if (empty($photoPaths)) {
        return array(true, $debug);
    }

    $resp = mundosex_http_request($session, $form['method'], $form['action_url'], array(
        'data' => $multipartData,
        'multipart' => true,
        'referer' => $editPage['effective_url'] ?? $editUrl,
        'headers' => array(
            'Origin: https://www.mundosexanuncio.com',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ),
        'timeout' => 180,
    ));
    if (!$resp['ok']) {
        return array(false, array_merge($debug, array(
            'error' => 'La subida de fotos devolvió error HTTP.',
            'details' => $resp,
        )));
    }

    $verify = mundosex_http_request($session, 'GET', $editUrl, array(
        'referer' => $resp['effective_url'],
        'timeout' => 120,
    ));
    if (!$verify['ok']) {
        return array(false, array_merge($debug, array(
            'error' => 'No se pudo reabrir la ficha tras subir las fotos.',
        )));
    }

    $verifyBody = (string)($verify['body'] ?? '');
    $existingAfter = 0;
    if (preg_match_all('/<span[^>]+class=["\']prev_image["\'][^>]*>\s*<img\b/iu', $verifyBody, $matchesAfter)) {
        $existingAfter = count((array)($matchesAfter[0] ?? array()));
    }
    $debug['photosDeleted'] = $existingBefore;
    $debug['photosUploaded'] = count($photoPaths);
    $debug['existing_photo_count_after'] = $existingAfter;
    if ($existingAfter !== count($photoPaths)) {
        return array(false, array_merge($debug, array(
            'error' => 'La web no confirmó el recuento final esperado de fotos tras la actualización.',
            'upload_hints' => mundosex_extract_upload_hints($verifyBody, $verify['effective_url']),
        )));
    }

    return array(true, $debug);
}

$session = mundosex_http_session(60);

try {
    if (MUNDOSEX_TEST_USER === 'PON_AQUI_EL_USUARIO' || MUNDOSEX_TEST_PASS === 'PON_AQUI_LA_PASSWORD') {
        echo json_encode(array(
            'ok' => false,
            'error' => 'Debes editar tools/test_mundosexanuncio.php y poner usuario y password reales en las constantes.',
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(1);
    }

    list($rawLoginResp, $rawLoginDebug) = mundosex_dump_page_debug($session, 'https://www.mundosexanuncio.com/misAnuncios', 'mundosex_login_debug');

    $mode = trim((string)MUNDOSEX_TEST_MODE);
    $result = array(
        'ok' => false,
        'mode' => $mode,
        'user' => MUNDOSEX_TEST_USER,
        'login_page_debug' => $rawLoginDebug,
        'tested_at' => now_datetime(),
    );

    if ($mode === 'listing_update') {
        $fields = mundosex_prepare_fields_payload(test_mundosex_fields());
        $photos = test_mundosex_photos();
        if (MUNDOSEX_TEST_LISTING_ID === 'PON_AQUI_EL_LISTING_ID') {
            echo json_encode(array(
                'ok' => false,
                'error' => 'Debes rellenar MUNDOSEX_TEST_LISTING_ID para probar listing_update.',
                'mode' => $mode,
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }

        list($okLogin, $loginMeta) = mundosex_login_session($session, MUNDOSEX_TEST_USER, MUNDOSEX_TEST_PASS);
        if (!$okLogin) {
            $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado'));
            $result['current_url'] = trim((string)($loginMeta['current_url'] ?? ''));
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }

        $editUrl = 'https://www.mundosexanuncio.com/publicar/editar/' . rawurlencode(trim((string)MUNDOSEX_TEST_LISTING_ID));
        list($editPage, $editDebug) = mundosex_dump_page_debug($session, $editUrl, 'mundosex_edit_debug');
        $result['listing_id'] = trim((string)MUNDOSEX_TEST_LISTING_ID);
        $result['fields_payload'] = $fields;
        $result['photos_payload'] = $photos;
        $result['edit_page_debug'] = $editDebug;

        if (!$editPage['ok']) {
            $result['error'] = 'No se pudo abrir la pantalla de edición del anuncio.';
            $result['current_url'] = trim((string)($editPage['effective_url'] ?? ''));
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }
        if (mundosex_page_has_login_form((string)$editPage['body'])) {
            $result['error'] = 'La pantalla de edición sigue devolviendo el login. Revisa autenticación o permisos del anuncio.';
            $result['current_url'] = trim((string)($editPage['effective_url'] ?? ''));
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }

        $form = mundosex_extract_edit_form((string)$editPage['body'], (string)$editPage['effective_url'], array_keys((array)$fields));
        if (!$form) {
            $result['error'] = 'No se encontró un formulario de edición autenticado reconocible.';
            $result['current_url'] = trim((string)($editPage['effective_url'] ?? ''));
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            exit(1);
        }
        $editableFields = mundosex_extract_editable_fields($form);
        $result['editable_fields_detected'] = $editableFields;
        $result['editable_fields_template_php'] = mundosex_build_fields_template_php($editableFields);
        $result['select_options_detected'] = mundosex_extract_select_options_map($editableFields);
        $selectedTagIds = mundosex_extract_selected_tag_ids((string)$editPage['body']);
        $result['selected_tag_ids_detected'] = $selectedTagIds;

        $expected = array();
        foreach ((array)$fields as $fieldKey => $value) {
            $found = mundosex_form_set_value($form, $fieldKey, $value);
            if (!$found) {
                $result['error'] = 'Campo editable no encontrado en la ficha: ' . $fieldKey;
                $result['current_url'] = trim((string)($editPage['effective_url'] ?? ''));
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
            $expected[$fieldKey] = $value;
        }

        $saveClicked = false;
        if (!empty($expected)) {
            list($okSubmitData, $submitData, $submitError) = mundosex_build_submit_data($form, array());
            if (!$okSubmitData) {
                $result['error'] = $submitError;
                $result['current_url'] = trim((string)($editPage['effective_url'] ?? ''));
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
            if (!empty($selectedTagIds)) {
                $submitData['tags[]'] = array_values($selectedTagIds);
            }
            $submitUrl = mundosex_submit_action_url((string)$form['action_url']);
            $result['submit_url_used'] = $submitUrl;
            $saveResp = mundosex_http_request($session, $form['method'], $submitUrl, array(
                'data' => $submitData,
                'multipart' => true,
                'referer' => $editPage['effective_url'],
                'headers' => array(
                    'Origin: https://www.mundosexanuncio.com',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ),
                'timeout' => 120,
            ));
            if (!$saveResp['ok']) {
                $result['error'] = 'No se pudo guardar el anuncio editado.';
                $result['current_url'] = trim((string)($saveResp['effective_url'] ?? ''));
                $result['save_response_preview'] = substr((string)($saveResp['body'] ?? ''), 0, 1500);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
            $saveClicked = true;

            $confirmResp = mundosex_http_request($session, 'GET', $editUrl, array(
                'referer' => $saveResp['effective_url'],
                'timeout' => 120,
            ));
            if (!$confirmResp['ok']) {
                $result['error'] = 'No se pudo reabrir la ficha para validar los cambios guardados.';
                $result['current_url'] = trim((string)($confirmResp['effective_url'] ?? ''));
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
            list($okValidate, $mismatches) = mundosex_validate_saved_fields($confirmResp['body'], $confirmResp['effective_url'], $expected);
            if (!$okValidate) {
                $result['error'] = 'La web devolvió la ficha, pero no confirmó todos los cambios: ' . implode(' ', $mismatches);
                $result['current_url'] = trim((string)($confirmResp['effective_url'] ?? ''));
                $result['save_response_preview'] = substr((string)($saveResp['body'] ?? ''), 0, 1500);
                $result['confirm_response_preview'] = substr((string)($confirmResp['body'] ?? ''), 0, 1500);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
            $editPage = $confirmResp;
            $editDebug['forms'] = mundosex_build_forms_debug((string)$confirmResp['body'], (string)$confirmResp['effective_url']);
            $editDebug['upload_hints'] = mundosex_extract_upload_hints((string)$confirmResp['body'], (string)$confirmResp['effective_url']);
            $result['edit_page_debug'] = $editDebug;
            $form = mundosex_extract_edit_form((string)$confirmResp['body'], (string)$confirmResp['effective_url'], array_keys((array)$fields));
            if ($form) {
                $editableFields = mundosex_extract_editable_fields($form);
                $result['editable_fields_detected'] = $editableFields;
                $result['editable_fields_template_php'] = mundosex_build_fields_template_php($editableFields);
                $result['select_options_detected'] = mundosex_extract_select_options_map($editableFields);
            }
        }

        $photoMeta = array(
            'photosPageOk' => false,
            'photosDeleted' => 0,
            'photosUploaded' => 0,
            'warnings' => array(),
        );
        if (!empty($photos)) {
            list($okPhotos, $photoMeta) = mundosex_try_update_listing_photos($session, $editPage, $editUrl, $photos);
            if (!$okPhotos) {
                $result['ok'] = false;
                $result['saveClicked'] = $saveClicked;
                $result['photo_result'] = $photoMeta;
                $result['error'] = trim((string)($photoMeta['error'] ?? 'No se pudieron actualizar las fotos del anuncio.'));
                $result['current_url'] = trim((string)($editUrl));
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
                exit(1);
            }
        }

        $result['ok'] = true;
        $result['saveClicked'] = $saveClicked;
        $result['photo_result'] = $photoMeta;
        $result['current_url'] = $editUrl;
    } else {
        list($okLogin, $meta) = mundosex_login_session($session, MUNDOSEX_TEST_USER, MUNDOSEX_TEST_PASS);
        $result = array_merge($result, array(
            'ok' => $okLogin ? true : false,
            'error' => trim((string)($meta['error'] ?? '')),
            'current_url' => trim((string)($meta['current_url'] ?? ($meta['login_url'] ?? ''))),
            'legal_gate_visible' => !empty($meta['legal_gate_visible']),
        ));
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
} finally {
    mundosex_http_cleanup_session($session);
}
