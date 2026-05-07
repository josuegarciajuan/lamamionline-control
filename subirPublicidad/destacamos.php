<?php

function destacamos_http_user_agent() {
    return 'Mozilla/5.0 (compatible; AtupuertaCRM/1.0; +https://www.destacamos.net)';
}

function destacamos_debug_dir() {
    $dir = rtrim(DATA_PATH, '/') . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function destacamos_debug_write_snapshot($prefix, $body, $ext = 'html') {
    $ext = trim((string)$ext) !== '' ? trim((string)$ext) : 'txt';
    $path = destacamos_debug_dir() . '/' . $prefix . '_' . date('Ymd_His') . '_' . substr(sha1(uniqid('', true)), 0, 8) . '.' . $ext;
    @file_put_contents($path, (string)$body);
    return $path;
}

function destacamos_debug_record_response(&$debugLog, $label, $resp, $saveBody = true) {
    if (!is_array($debugLog)) {
        return;
    }
    if (!isset($debugLog['steps']) || !is_array($debugLog['steps'])) {
        $debugLog['steps'] = array();
    }
    $body = (string)($resp['body'] ?? '');
    $step = array(
        'label' => (string)$label,
        'ok' => !empty($resp['ok']),
        'http_code' => (int)($resp['http_code'] ?? 0),
        'elapsed_ms' => (int)($resp['elapsed_ms'] ?? 0),
        'effective_url' => trim((string)($resp['effective_url'] ?? '')),
        'content_type' => trim((string)($resp['content_type'] ?? '')),
        'error' => trim((string)($resp['error'] ?? '')),
        'body_length' => strlen($body),
        'body_preview' => substr($body, 0, 1800),
    );
    if ($saveBody && $body !== '') {
        $safeLabel = preg_replace('/[^a-z0-9_]+/i', '_', (string)$label);
        $step['saved_html_path'] = destacamos_debug_write_snapshot('destacamos_' . $safeLabel, $body, 'html');
    } else {
        $step['saved_html_path'] = '';
    }
    $debugLog['steps'][] = $step;
}

function destacamos_human_defaults() {
    return array(
        'enabled' => false,
        'default_min_ms' => 900,
        'default_max_ms' => 2200,
        'reading_min_ms' => 1200,
        'reading_max_ms' => 2800,
        'click_min_ms' => 350,
        'click_max_ms' => 1100,
        'typing_char_min_ms' => 25,
        'typing_char_max_ms' => 65,
        'typing_extra_min_ms' => 180,
        'typing_extra_max_ms' => 700,
        'typing_max_ms' => 4500,
        'photo_between_min_ms' => 1800,
        'photo_between_max_ms' => 4200,
    );
}

function destacamos_human_options($session) {
    $defaults = destacamos_human_defaults();
    $custom = is_array($session['human'] ?? null) ? $session['human'] : array();
    return array_merge($defaults, $custom);
}

function destacamos_human_is_enabled($session) {
    $opts = destacamos_human_options($session);
    return !empty($opts['enabled']);
}

function destacamos_human_random_ms($minMs, $maxMs) {
    $minMs = max(0, (int)$minMs);
    $maxMs = max($minMs, (int)$maxMs);
    if ($maxMs <= $minMs) {
        return $minMs;
    }
    try {
        return random_int($minMs, $maxMs);
    } catch (Throwable $e) {
        return mt_rand($minMs, $maxMs);
    }
}

function destacamos_human_sleep_ms($ms) {
    $ms = max(0, (int)$ms);
    if ($ms > 0) {
        usleep($ms * 1000);
    }
}

function destacamos_human_pause(&$session, $label, $minMs = null, $maxMs = null) {
    if (!destacamos_human_is_enabled($session)) {
        return 0;
    }
    $opts = destacamos_human_options($session);
    $minMs = $minMs === null ? (int)$opts['default_min_ms'] : (int)$minMs;
    $maxMs = $maxMs === null ? (int)$opts['default_max_ms'] : (int)$maxMs;
    $delay = destacamos_human_random_ms($minMs, $maxMs);
    destacamos_human_sleep_ms($delay);
    if (!isset($session['human_trace']) || !is_array($session['human_trace'])) {
        $session['human_trace'] = array();
    }
    $session['human_trace'][] = array(
        'label' => (string)$label,
        'delay_ms' => $delay,
        'at' => date('Y-m-d H:i:s'),
    );
    return $delay;
}

function destacamos_human_typing_pause(&$session, $label, $texts) {
    if (!destacamos_human_is_enabled($session)) {
        return 0;
    }
    $opts = destacamos_human_options($session);
    $totalChars = 0;
    foreach ((array)$texts as $text) {
        if (is_array($text)) {
            foreach ($text as $sub) {
                $totalChars += function_exists('mb_strlen') ? mb_strlen((string)$sub, 'UTF-8') : strlen((string)$sub);
            }
            continue;
        }
        $totalChars += function_exists('mb_strlen') ? mb_strlen((string)$text, 'UTF-8') : strlen((string)$text);
    }
    $perChar = destacamos_human_random_ms((int)$opts['typing_char_min_ms'], (int)$opts['typing_char_max_ms']);
    $extra = destacamos_human_random_ms((int)$opts['typing_extra_min_ms'], (int)$opts['typing_extra_max_ms']);
    $delay = min((int)$opts['typing_max_ms'], ($totalChars * $perChar) + $extra);
    destacamos_human_sleep_ms($delay);
    if (!isset($session['human_trace']) || !is_array($session['human_trace'])) {
        $session['human_trace'] = array();
    }
    $session['human_trace'][] = array(
        'label' => (string)$label,
        'delay_ms' => $delay,
        'chars' => $totalChars,
        'at' => date('Y-m-d H:i:s'),
    );
    return $delay;
}

function destacamos_http_cookie_file() {
    $dir = rtrim(DATA_PATH, '/') . '/publicista/tmp';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/destacamos_' . generate_id('sess') . '.cookie';
}

function destacamos_http_cleanup_session($session) {
    $cookieFile = trim((string)($session['cookie_file'] ?? ''));
    if ($cookieFile !== '' && is_file($cookieFile)) {
        @unlink($cookieFile);
    }
}

function destacamos_http_session($timeoutSec = 90) {
    return array(
        'cookie_file' => destacamos_http_cookie_file(),
        'timeout' => max(15, (int)$timeoutSec),
    );
}

function destacamos_http_build_url($baseUrl, $relativeUrl) {
    $relativeUrl = html_entity_decode(trim((string)$relativeUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

function destacamos_http_request($session, $method, $url, $options = array()) {
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
    curl_setopt($ch, CURLOPT_USERAGENT, destacamos_http_user_agent());
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? $data : array());
        } else {
            $payload = is_array($data) ? http_build_query($data) : (string)$data;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $startedAt = microtime(true);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);
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
        'elapsed_ms' => $elapsedMs,
    );
}

function destacamos_classify_runtime_error($message, $phase = '') {
    $msg = destacamos_strtolower(trim((string)$message));
    $phase = trim((string)$phase);
    if ($msg === '') {
        return array('error_code' => 'runtime_error', 'error_category' => 'unknown', 'error_phase' => $phase);
    }
    if (strpos($msg, 'login') !== false) {
        return array('error_code' => 'login_failed', 'error_category' => 'auth', 'error_phase' => $phase);
    }
    if (strpos($msg, 'formulario de edición') !== false || strpos($msg, 'formulario de edicion') !== false) {
        return array('error_code' => 'edit_form_missing', 'error_category' => 'remote_markup', 'error_phase' => $phase);
    }
    if (strpos($msg, 'abrir la pantalla de edición') !== false || strpos($msg, 'abrir la pantalla de edicion') !== false) {
        return array('error_code' => 'edit_page_unreachable', 'error_category' => 'network_or_remote', 'error_phase' => $phase);
    }
    if (strpos($msg, 'guardar el anuncio editado') !== false) {
        return array('error_code' => 'save_submit_failed', 'error_category' => 'network_or_remote', 'error_phase' => $phase);
    }
    if (strpos($msg, 'no confirmó todos los cambios') !== false || strpos($msg, 'no confirmo todos los cambios') !== false) {
        return array('error_code' => 'save_not_confirmed', 'error_category' => 'post_save_validation', 'error_phase' => $phase);
    }
    return array('error_code' => 'runtime_error', 'error_category' => 'runtime', 'error_phase' => $phase);
}

function destacamos_html_normalize($text) {
    $text = html_entity_decode((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string)$text);
}

function destacamos_strtolower($text) {
    $text = (string)$text;
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function destacamos_strpos($haystack, $needle) {
    return function_exists('mb_strpos') ? mb_strpos((string)$haystack, (string)$needle, 0, 'UTF-8') : strpos((string)$haystack, (string)$needle);
}

function destacamos_phone_digits($phone) {
    return preg_replace('/\D+/', '', (string)$phone);
}

function destacamos_html_load_xpath($html) {
    if (!class_exists('DOMDocument')) return array(null, null);
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . (string)$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return array($dom, new DOMXPath($dom));
}

function destacamos_parse_html_attributes($chunk) {
    $attrs = array();
    if (!preg_match_all('/([a-zA-Z_:][a-zA-Z0-9_:\-\.]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/s', (string)$chunk, $matches, PREG_SET_ORDER)) {
        return $attrs;
    }
    foreach ($matches as $match) {
        $name = strtolower(trim((string)($match[1] ?? '')));
        $value = '';
        if (isset($match[3]) && $match[3] !== '') $value = (string)$match[3];
        elseif (isset($match[4]) && $match[4] !== '') $value = (string)$match[4];
        else $value = (string)($match[5] ?? '');
        if ($name !== '') $attrs[$name] = $value;
    }
    return $attrs;
}

function destacamos_extract_form_fallback($html, $baseUrl, $criteria = array()) {
    $html = (string)$html;
    if ($html === '') return null;

    $inputNames = array_map('strval', (array)($criteria['input_names'] ?? array()));
    $inputIds = array_map('strval', (array)($criteria['input_ids'] ?? array()));
    $formId = trim((string)($criteria['form_id'] ?? ''));
    $formName = trim((string)($criteria['form_name'] ?? ''));
    $buttonIds = array_map('strval', (array)($criteria['button_ids'] ?? array()));

    if (!preg_match_all('~<form\b([^>]*)>(.*?)</form>~is', $html, $forms, PREG_SET_ORDER)) {
        return null;
    }

    foreach ($forms as $formMatch) {
        $attrs = destacamos_parse_html_attributes($formMatch[1] ?? '');
        $innerHtml = (string)($formMatch[2] ?? '');
        $matches = false;

        if ($formId !== '' && trim((string)($attrs['id'] ?? '')) === $formId) {
            $matches = true;
        }
        if (!$matches && $formName !== '' && trim((string)($attrs['name'] ?? '')) === $formName) {
            $matches = true;
        }
        if (!$matches && !empty($inputNames)) {
            foreach ($inputNames as $name) {
                if (preg_match('~<(?:input|textarea|select)\b[^>]*\bname=(["\'])' . preg_quote($name, '~') . '\1~i', $innerHtml)) {
                    $matches = true;
                    break;
                }
            }
        }
        if (!$matches && !empty($inputIds)) {
            foreach ($inputIds as $id) {
                if (preg_match('~<(?:input|textarea|select)\b[^>]*\bid=(["\'])' . preg_quote($id, '~') . '\1~i', $innerHtml)) {
                    $matches = true;
                    break;
                }
            }
        }
        if (!$matches && !empty($buttonIds)) {
            foreach ($buttonIds as $id) {
                if (preg_match('~<(?:input|button)\b[^>]*\bid=(["\'])' . preg_quote($id, '~') . '\1~i', $innerHtml)) {
                    $matches = true;
                    break;
                }
            }
        }
        if (!$matches) {
            continue;
        }

        $method = strtoupper(trim((string)($attrs['method'] ?? 'POST')));
        $actionUrl = destacamos_http_build_url($baseUrl, (string)($attrs['action'] ?? ''));
        $enctype = trim((string)($attrs['enctype'] ?? ''));
        $form = array(
            'action_url' => $actionUrl,
            'method' => $method !== '' ? $method : 'POST',
            'id' => trim((string)($attrs['id'] ?? '')),
            'enctype' => $enctype,
            'fields' => array(),
            'submitters' => array(),
            'data' => array(),
        );

        if (preg_match_all('~<input\b([^>]*)>~is', $innerHtml, $inputs, PREG_SET_ORDER)) {
            foreach ($inputs as $inputMatch) {
                $inputAttrs = destacamos_parse_html_attributes($inputMatch[1] ?? '');
                $type = strtolower(trim((string)($inputAttrs['type'] ?? 'text')));
                $name = trim((string)($inputAttrs['name'] ?? ''));
                $id = trim((string)($inputAttrs['id'] ?? ''));
                $value = (string)($inputAttrs['value'] ?? '');
                $checked = array_key_exists('checked', $inputAttrs);
                $field = array(
                    'tag' => 'input',
                    'type' => $type,
                    'name' => $name,
                    'id' => $id,
                    'value' => $value,
                    'checked' => $checked,
                );
                if (in_array($type, array('submit', 'button', 'image'), true)) {
                    $form['submitters'][] = array(
                        'name' => $name,
                        'value' => $value,
                        'id' => $id,
                        'type' => $type,
                    );
                    continue;
                }
                $form['fields'][] = $field;
                if ($name === '') continue;
                if (in_array($type, array('checkbox', 'radio'), true)) {
                    if ($checked) {
                        destacamos_form_data_assign($form['data'], $name, $value !== '' ? $value : 'on', true);
                    }
                    continue;
                }
                destacamos_form_data_assign($form['data'], $name, $value);
            }
        }

        if (preg_match_all('~<button\b([^>]*)>(.*?)</button>~is', $innerHtml, $buttons, PREG_SET_ORDER)) {
            foreach ($buttons as $buttonMatch) {
                $buttonAttrs = destacamos_parse_html_attributes($buttonMatch[1] ?? '');
                $type = strtolower(trim((string)($buttonAttrs['type'] ?? 'submit')));
                if (!in_array($type, array('submit', 'button'), true)) continue;
                $form['submitters'][] = array(
                    'name' => trim((string)($buttonAttrs['name'] ?? '')),
                    'value' => trim((string)($buttonAttrs['value'] ?? destacamos_html_normalize($buttonMatch[2] ?? ''))),
                    'id' => trim((string)($buttonAttrs['id'] ?? '')),
                    'type' => $type,
                );
            }
        }

        return $form;
    }

    return null;
}

function destacamos_xpath_has_class($className) {
    $className = trim((string)$className);
    return "contains(concat(' ', normalize-space(@class), ' '), ' " . $className . " ')";
}

function destacamos_node_attr($node, $name) {
    return ($node instanceof DOMElement && $node->hasAttribute($name)) ? (string)$node->getAttribute($name) : '';
}

function destacamos_form_data_assign(&$data, $name, $value, $append = false) {
    if ($name === '') return;
    $isArrayName = substr($name, -2) === '[]';
    if ($append || $isArrayName) {
        if (!isset($data[$name]) || !is_array($data[$name])) {
            $data[$name] = array();
        }
        $data[$name][] = $value;
        return;
    }

    if (!array_key_exists($name, $data) || is_array($data[$name])) {
        $data[$name] = $value;
        return;
    }

    $currentValue = trim((string)$data[$name]);
    $incomingValue = trim((string)$value);

    if ($currentValue === '' && $incomingValue !== '') {
        $data[$name] = $value;
        return;
    }

    if ($currentValue !== '' && $incomingValue === '') {
        return;
    }

    if ($currentValue === '') {
        $data[$name] = $value;
    }
}

function destacamos_form_build($formNode, $baseUrl) {
    $form = array(
        'action_url' => destacamos_http_build_url($baseUrl, destacamos_node_attr($formNode, 'action')),
        'method' => strtoupper(trim((string)(destacamos_node_attr($formNode, 'method') ?: 'POST'))),
        'id' => destacamos_node_attr($formNode, 'id'),
        'enctype' => trim((string)destacamos_node_attr($formNode, 'enctype')),
        'fields' => array(),
        'submitters' => array(),
        'data' => array(),
    );

    foreach ($formNode->getElementsByTagName('input') as $input) {
        $type = strtolower(trim((string)(destacamos_node_attr($input, 'type') ?: 'text')));
        $name = trim((string)destacamos_node_attr($input, 'name'));
        $id = trim((string)destacamos_node_attr($input, 'id'));
        $value = (string)destacamos_node_attr($input, 'value');
        $disabled = $input->hasAttribute('disabled');
        if ($disabled) continue;

        if (in_array($type, array('submit', 'button', 'image'), true)) {
            $form['submitters'][] = array('name' => $name, 'value' => $value, 'id' => $id, 'type' => $type);
            continue;
        }
        if ($type === 'file') {
            $form['fields'][] = array('tag' => 'input', 'type' => 'file', 'name' => $name, 'id' => $id, 'value' => '');
            continue;
        }

        $field = array(
            'tag' => 'input',
            'type' => $type,
            'name' => $name,
            'id' => $id,
            'value' => $value,
            'checked' => $input->hasAttribute('checked'),
        );
        $form['fields'][] = $field;

        if ($name === '') continue;
        if (in_array($type, array('checkbox', 'radio'), true)) {
            if ($input->hasAttribute('checked')) {
                destacamos_form_data_assign($form['data'], $name, $value !== '' ? $value : 'on', true);
            }
            continue;
        }
        destacamos_form_data_assign($form['data'], $name, $value);
    }

    foreach ($formNode->getElementsByTagName('textarea') as $textarea) {
        $name = trim((string)destacamos_node_attr($textarea, 'name'));
        $id = trim((string)destacamos_node_attr($textarea, 'id'));
        if ($textarea->hasAttribute('disabled')) continue;
        $value = (string)$textarea->textContent;
        $form['fields'][] = array('tag' => 'textarea', 'type' => 'textarea', 'name' => $name, 'id' => $id, 'value' => $value);
        if ($name !== '') {
            destacamos_form_data_assign($form['data'], $name, $value);
        }
    }

    foreach ($formNode->getElementsByTagName('select') as $select) {
        $name = trim((string)destacamos_node_attr($select, 'name'));
        $id = trim((string)destacamos_node_attr($select, 'id'));
        if ($select->hasAttribute('disabled')) continue;
        $multiple = $select->hasAttribute('multiple');
        $options = array();
        $selectedValues = array();
        foreach ($select->getElementsByTagName('option') as $option) {
            $optionValue = (string)destacamos_node_attr($option, 'value');
            $label = destacamos_html_normalize($option->textContent);
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
            destacamos_form_data_assign($form['data'], $name, $field['value'], $multiple);
        }
    }

    foreach ($formNode->getElementsByTagName('button') as $button) {
        $type = strtolower(trim((string)(destacamos_node_attr($button, 'type') ?: 'submit')));
        if (!in_array($type, array('submit', 'button'), true)) continue;
        $form['submitters'][] = array(
            'name' => trim((string)destacamos_node_attr($button, 'name')),
            'value' => (string)(destacamos_node_attr($button, 'value') ?: destacamos_html_normalize($button->textContent)),
            'id' => trim((string)destacamos_node_attr($button, 'id')),
            'type' => $type,
        );
    }

    return $form;
}

function destacamos_extract_form($html, $baseUrl, $criteria = array()) {
    list($dom, $xpath) = destacamos_html_load_xpath($html);
    if (!$dom || !$xpath) {
        return destacamos_extract_form_fallback($html, $baseUrl, $criteria);
    }
    $forms = $xpath->query('//form');
    if (!$forms) {
        return destacamos_extract_form_fallback($html, $baseUrl, $criteria);
    }

    $inputNames = array_map('strval', (array)($criteria['input_names'] ?? array()));
    $inputIds = array_map('strval', (array)($criteria['input_ids'] ?? array()));
    $formId = trim((string)($criteria['form_id'] ?? ''));
    $formName = trim((string)($criteria['form_name'] ?? ''));
    $buttonIds = array_map('strval', (array)($criteria['button_ids'] ?? array()));

    foreach ($forms as $formNode) {
        if (!($formNode instanceof DOMElement)) continue;
        if ($formId !== '' && destacamos_node_attr($formNode, 'id') === $formId) {
            return destacamos_form_build($formNode, $baseUrl);
        }
        if ($formName !== '' && destacamos_node_attr($formNode, 'name') === $formName) {
            return destacamos_form_build($formNode, $baseUrl);
        }

        $matches = false;
        if (!empty($inputNames)) {
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
        if (!$matches && !empty($buttonIds)) {
            foreach ($buttonIds as $id) {
                $query = './/*[@id="' . addslashes($id) . '"]';
                if ($xpath->query($query, $formNode)->length > 0) {
                    $matches = true;
                    break;
                }
            }
        }
        if ($matches) {
            return destacamos_form_build($formNode, $baseUrl);
        }
    }

    if ($forms->length > 0 && $forms->item(0) instanceof DOMElement) {
        return destacamos_form_build($forms->item(0), $baseUrl);
    }
    return destacamos_extract_form_fallback($html, $baseUrl, $criteria);
}

function destacamos_form_find_field_indexes($form, $fieldKey) {
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

function destacamos_form_normalize_choice($value) {
    return destacamos_strtolower(destacamos_html_normalize((string)$value));
}

function destacamos_form_normalize_choice_list($values) {
    $normalized = array();
    foreach ((array)$values as $value) {
        $token = destacamos_form_normalize_choice($value);
        if ($token === '') continue;
        $normalized[$token] = $token;
    }
    return array_values($normalized);
}

function destacamos_form_choice_matches($candidate, $desiredValues) {
    $candidate = destacamos_form_normalize_choice($candidate);
    if ($candidate === '') return false;
    foreach (destacamos_form_normalize_choice_list($desiredValues) as $desired) {
        if ($candidate === $desired) {
            return true;
        }
    }
    return false;
}

function destacamos_form_set_value(&$form, $fieldKey, $value) {
    $indexes = destacamos_form_find_field_indexes($form, $fieldKey);
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
                if (destacamos_form_choice_matches($optionValue, $targets) || destacamos_form_choice_matches($optionLabel, $targets)) {
                    $selectedValues[] = $optionValue;
                    if (empty($field['multiple'])) {
                        break;
                    }
                }
            }
            if (empty($field['multiple'])) {
                $currentValue = is_scalar($field['value'] ?? null) ? (string)$field['value'] : '';
                $selectedValue = (string)($selectedValues[0] ?? '');
                if ($selectedValue === '') {
                    if ($currentValue !== '') {
                        $selectedValue = $currentValue;
                    } elseif (!empty($field['options'][0]['value'])) {
                        $selectedValue = (string)$field['options'][0]['value'];
                    }
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
                $shouldCheck = destacamos_form_choice_matches($fieldValue, $value);
                $form['fields'][$index]['checked'] = $shouldCheck;
                if ($shouldCheck) {
                    destacamos_form_data_assign($form['data'], $name, $fieldValue, true);
                }
                continue;
            }

            $truthy = is_bool($value) ? $value : in_array(destacamos_form_normalize_choice($value), array('1', 'true', 'si', 'sí', 'yes', 'on', destacamos_form_normalize_choice($fieldValue)), true);
            if ($hasMultipleChoices && !is_bool($value)) {
                if ($index === $firstIndex) {
                    unset($form['data'][$name]);
                }
                $truthy = destacamos_form_choice_matches($fieldValue, array($value));
            }

            if ($truthy) {
                $form['fields'][$index]['checked'] = true;
                if ($hasMultipleChoices) {
                    destacamos_form_data_assign($form['data'], $name, $fieldValue, true);
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

function destacamos_form_pick_submit(&$form, $preferredIds = array(), $preferredKeywords = array()) {
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
            $haystack = destacamos_strtolower(destacamos_html_normalize(($submitter['id'] ?? '') . ' ' . ($submitter['name'] ?? '') . ' ' . ($submitter['value'] ?? '')));
            if ($keyword !== '' && destacamos_strpos($haystack, destacamos_strtolower($keyword)) !== false) {
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

function destacamos_page_has_logout($html) {
    return preg_match('/>\s*Salir\s*</iu', (string)$html) === 1;
}

function destacamos_login_timezone_value() {
    $tz = trim((string)date_default_timezone_get());
    if ($tz === '') $tz = 'Europe/Madrid';
    return base64_encode($tz);
}

function destacamos_login_fingerprint_value($username) {
    $seed = trim((string)$username) . '|' . destacamos_http_user_agent() . '|' . date_default_timezone_get() . '|destacamos';
    return substr(sha1($seed), 0, 20);
}

function destacamos_extract_login_error_message($html) {
    $html = (string)$html;
    list($dom, $xpath) = destacamos_html_load_xpath($html);
    if ($dom && $xpath) {
        $queries = array(
            '//*[contains(@class, "error")]',
            '//*[contains(@class, "warning")]',
            '//*[contains(@class, "alert")]',
            '//*[contains(@class, "notice")]',
            '//*[contains(@id, "error")]',
            '//*[contains(@id, "warning")]',
        );
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes) continue;
            foreach ($nodes as $node) {
                $text = destacamos_html_normalize($node->textContent ?? '');
                $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                if ($text !== '' && $len >= 6) {
                    return $text;
                }
            }
        }
    }

    if (preg_match('/(bloquead[oa][^<\n\r]{0,180}|demasiados intentos[^<\n\r]{0,180}|usuario o contraseña[^<\n\r]{0,180}|contraseña incorrecta[^<\n\r]{0,180}|cuenta[^<\n\r]{0,180}bloquead[oa][^<\n\r]{0,180})/iu', $html, $match)) {
        return destacamos_html_normalize($match[1]);
    }

    return '';
}

function destacamos_extract_edit_error_messages($html) {
    $html = (string)$html;
    $messages = array();

    $pushMessage = function($text) use (&$messages) {
        $text = destacamos_html_normalize($text);
        if ($text === '') {
            return;
        }
        $messages[$text] = $text;
    };

    list($dom, $xpath) = destacamos_html_load_xpath($html);
    if ($dom && $xpath) {
        $queries = array(
            '//*[contains(concat(" ", normalize-space(@class), " "), " error ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " errors ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " alert ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " warning ")]',
            '//*[@id="error"]',
            '//*[@id="errors"]',
            '//*[contains(@data-testid, "error")]',
        );
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes) continue;
            foreach ($nodes as $node) {
                $pushMessage($node->textContent ?? '');
            }
        }
    }

    // Ojo: no rastreamos por regex global los errores de teléfono/CP porque la web
    // incrusta esos textos dentro de JS estático y eso producía falsos positivos.
    if (empty($messages)) {
        $knownPatterns = array(
            '/Escribe un texto un poco diferente(?:\s+al\s+del\s+resto\s+de\s+anuncios)?/iu',
            '/Escribe un t[ií]tulo un poco diferente(?:\s+al\s+del\s+resto\s+de\s+anuncios)?/iu',
            '/Parece que est[aá]s intentando camuflar algunas expresiones prohibidas[^<\n\r]*?revisa el contenido de tu perfil\.?/iu',
        );
        foreach ($knownPatterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $pushMessage($match[0]);
            }
        }
    }

    return array_values($messages);
}

function destacamos_edit_error_code($messages) {
    $joined = destacamos_strtolower(implode(' ', array_values((array)$messages)));
    if ($joined === '') {
        return '';
    }

    $duplicateNeedles = array(
        'mismo texto',
        'mismo título',
        'mismo titulo',
        'texto un poco diferente',
        'título un poco diferente',
        'titulo un poco diferente',
    );
    foreach ($duplicateNeedles as $needle) {
        if (destacamos_strpos($joined, $needle) !== false) {
            return 'duplicate_copy';
        }
    }

    foreach (array(
        'camuflar algunas expresiones prohibidas',
        'expresiones prohibidas',
        'revisa el contenido de tu perfil',
    ) as $needle) {
        if (destacamos_strpos($joined, $needle) !== false) {
            return 'content_moderation';
        }
    }

    return 'validation_error';
}


function destacamos_phone_normalize_spanish($phone) {
    $digits = destacamos_phone_digits($phone);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) > 9) {
        if (substr($digits, 0, 2) === '34' && strlen($digits) >= 11) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }
    }
    return preg_match('/^[6-9][0-9]{8}$/', $digits) ? $digits : '';
}

function destacamos_zip_normalize_spanish($zip) {
    $digits = preg_replace('/\D+/', '', (string)$zip);
    if (strlen($digits) > 5) {
        $digits = substr($digits, 0, 5);
    }
    if (!preg_match('/^(?:0[1-9]|[1-4][0-9]|5[0-2])[0-9]{3}$/', $digits)) {
        return '';
    }
    return $digits;
}

function destacamos_messages_include_phone_or_zip_error($messages) {
    $joined = destacamos_strtolower(implode(' ', array_values((array)$messages)));
    if ($joined === '') {
        return false;
    }
    foreach (array(
        'teléfono español válido',
        'telefono español valido',
        'teléfono español valido',
        'telefono español válido',
        'código postal español válido',
        'codigo postal espanol valido',
        'código postal español valido',
        'codigo postal español válido'
    ) as $needle) {
        if (destacamos_strpos($joined, destacamos_strtolower($needle)) !== false) {
            return true;
        }
    }
    return false;
}

function destacamos_capture_edit_form_values($form, $fieldKeys) {
    $snapshot = array();
    foreach ((array)$fieldKeys as $fieldKey) {
        $fieldKey = trim((string)$fieldKey);
        if ($fieldKey === '') {
            continue;
        }
        list($found, $currentValue) = destacamos_form_read_value($form, $fieldKey);
        if ($found) {
            $snapshot[$fieldKey] = is_array($currentValue) ? $currentValue : trim((string)$currentValue);
        }
    }
    return $snapshot;
}

function destacamos_apply_edit_field(&$form, $fieldKey, $desiredValue, $fallbackValue = null) {
    $fieldKey = trim((string)$fieldKey);
    if ($fieldKey === '') {
        return false;
    }

    $finalValue = $desiredValue;
    if ($fieldKey === 'telefono') {
        $normalizedDesired = destacamos_phone_normalize_spanish($desiredValue);
        $normalizedFallback = destacamos_phone_normalize_spanish($fallbackValue);
        $finalValue = $normalizedDesired !== '' ? $normalizedDesired : $normalizedFallback;
    } elseif ($fieldKey === 'zip') {
        $normalizedDesired = destacamos_zip_normalize_spanish($desiredValue);
        $normalizedFallback = destacamos_zip_normalize_spanish($fallbackValue);
        $finalValue = $normalizedDesired !== '' ? $normalizedDesired : $normalizedFallback;
    } elseif (trim((string)$desiredValue) === '' && $fallbackValue !== null) {
        $finalValue = $fallbackValue;
    }

    if ($finalValue === null) {
        $finalValue = '';
    }

    return destacamos_form_set_value($form, $fieldKey, $finalValue);
}



function destacamos_login_session(&$session, $username, $password, &$debugLog = null) {
    $loginUrl = 'https://www.destacamos.net/login.php?loc=browse_listings.php';
    $page = destacamos_http_request($session, 'GET', $loginUrl, array('timeout' => 60));
    destacamos_debug_record_response($debugLog, 'login_page', $page);
    if (!$page['ok']) {
        return array(false, array('error' => 'No se pudo abrir la página de login: ' . ($page['error'] !== '' ? $page['error'] : ('HTTP ' . $page['http_code']))));
    }

    $form = destacamos_extract_form($page['body'], $page['effective_url'], array(
        'form_name' => 'login',
        'input_ids' => array('username', 'password'),
        'input_names' => array('username', 'password'),
    ));
    if (!$form) {
        return array(false, array('error' => 'No se encontró el formulario de login de Destacamos.'));
    }

    destacamos_form_set_value($form, 'username', $username);
    destacamos_form_set_value($form, 'password', $password);
    destacamos_form_set_value($form, 'zmt', destacamos_login_timezone_value());
    destacamos_form_set_value($form, 'vjs', destacamos_login_fingerprint_value($username));
    destacamos_form_set_value($form, 'remember', true);
    destacamos_form_pick_submit($form, array(), array('entrar', 'login', 'acceder'));
    destacamos_human_pause($session, 'login_page_read', destacamos_human_options($session)['reading_min_ms'], destacamos_human_options($session)['reading_max_ms']);
    destacamos_human_typing_pause($session, 'login_typing', array($username, $password));
    $opts = destacamos_human_options($session);
    destacamos_human_pause($session, 'login_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);

    $resp = destacamos_http_request($session, $form['method'], $form['action_url'], array(
        'data' => $form['data'],
        'referer' => $page['effective_url'],
        'headers' => array(
            'Origin: https://www.destacamos.net',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ),
        'timeout' => 60,
    ));
    destacamos_debug_record_response($debugLog, 'login_submit', $resp);
    if (!$resp['ok']) {
        return array(false, array('error' => 'No se pudo enviar el login: ' . ($resp['error'] !== '' ? $resp['error'] : ('HTTP ' . $resp['http_code']))));
    }
    if (!destacamos_page_has_logout($resp['body'])) {
        $errorMessage = destacamos_extract_login_error_message($resp['body']);
        return array(false, array(
            'error' => $errorMessage !== '' ? $errorMessage : 'Login no confirmado. Verifica usuario y contraseña.',
            'current_url' => $resp['effective_url'],
            'posted_fields' => array(
                'username' => $username,
                'zmt' => (string)($form['data']['zmt'] ?? ''),
                'vjs' => (string)($form['data']['vjs'] ?? ''),
                'remember' => isset($form['data']['remember']) ? (string)$form['data']['remember'] : '',
            ),
        ));
    }

    return array(true, array('login_url' => $resp['effective_url']));
}

function destacamos_session_is_authenticated(&$session, $username = '') {
    $checkUrl = 'https://www.destacamos.net/browse_listings.php';
    $resp = destacamos_http_request($session, 'GET', $checkUrl, array('timeout' => 45));
    if (!$resp['ok']) {
        return array(false, array(
            'error' => 'No se pudo comprobar la sesión existente.',
            'current_url' => trim((string)($resp['effective_url'] ?? '')),
        ));
    }
    if (destacamos_page_has_logout($resp['body'])) {
        return array(true, array(
            'current_url' => trim((string)($resp['effective_url'] ?? '')),
        ));
    }
    return array(false, array(
        'error' => 'La sesión existente ya no parece autenticada.',
        'current_url' => trim((string)($resp['effective_url'] ?? '')),
    ));
}

function destacamos_payload_human_settings($payload) {
    $defaults = destacamos_human_defaults();
    $inline = is_array($payload['humanize'] ?? null) ? $payload['humanize'] : array();
    $settings = array_merge($defaults, $inline);
    if (!is_array($payload['humanize'] ?? null)) {
        $settings['enabled'] = !empty($payload['humanize']);
    } elseif (array_key_exists('enabled', $inline)) {
        $settings['enabled'] = !empty($inline['enabled']);
    } else {
        $settings['enabled'] = true;
    }
    $map = array(
        'humanDefaultMinMs' => 'default_min_ms',
        'humanDefaultMaxMs' => 'default_max_ms',
        'humanReadingMinMs' => 'reading_min_ms',
        'humanReadingMaxMs' => 'reading_max_ms',
        'humanClickMinMs' => 'click_min_ms',
        'humanClickMaxMs' => 'click_max_ms',
        'humanTypingCharMinMs' => 'typing_char_min_ms',
        'humanTypingCharMaxMs' => 'typing_char_max_ms',
        'humanTypingExtraMinMs' => 'typing_extra_min_ms',
        'humanTypingExtraMaxMs' => 'typing_extra_max_ms',
        'humanTypingMaxMs' => 'typing_max_ms',
        'humanPhotoBetweenMinMs' => 'photo_between_min_ms',
        'humanPhotoBetweenMaxMs' => 'photo_between_max_ms',
    );
    foreach ($map as $payloadKey => $settingKey) {
        if (array_key_exists($payloadKey, $payload)) {
            $settings[$settingKey] = (int)$payload[$payloadKey];
        }
    }
    return $settings;
}

function destacamos_compare_values($current, $expected, $fieldKey = '') {
    if (is_bool($expected)) {
        return (bool)$current === $expected;
    }
    if (is_array($expected)) {
        $currentList = is_array($current) ? $current : array($current);
        $a = destacamos_form_normalize_choice_list($currentList);
        $b = destacamos_form_normalize_choice_list($expected);
        sort($a);
        sort($b);
        return $a === $b;
    }
    $expected = (string)$expected;
    $current = (string)$current;
    if ($fieldKey === 'telefono') {
        $a = destacamos_phone_digits($current);
        $b = destacamos_phone_digits($expected);
        return $a !== '' && $b !== '' ? (substr($a, -9) === substr($b, -9)) : (trim($current) === trim($expected));
    }
    return destacamos_strtolower(destacamos_html_normalize($current)) === destacamos_strtolower(destacamos_html_normalize($expected));
}

function destacamos_form_read_value($form, $fieldKey) {
    $indexes = destacamos_form_find_field_indexes($form, $fieldKey);
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

function destacamos_validate_saved_fields($html, $baseUrl, $expected) {
    $form = destacamos_extract_form($html, $baseUrl, array(
        'form_id' => 'formularioeditar',
        'button_ids' => array('guardarcambios'),
    ));
    if (!$form) {
        return array(false, array('No se pudo volver a leer el formulario de edición tras guardar.'));
    }

    $mismatches = array();
    foreach ((array)$expected as $fieldKey => $expectedValue) {
        list($found, $currentValue) = destacamos_form_read_value($form, $fieldKey);
        if (!$found) {
            $mismatches[] = 'Campo no encontrado tras guardar: ' . $fieldKey;
            continue;
        }

        $indexes = destacamos_form_find_field_indexes($form, $fieldKey);
        $field = $form['fields'][$indexes[0]];
        if (($field['tag'] ?? '') === 'select' && !is_array($expectedValue)) {
            $selectedValue = (string)$currentValue;
            $selectedLabel = $selectedValue;
            foreach ((array)($field['options'] ?? array()) as $option) {
                if ((string)($option['value'] ?? '') === $selectedValue) {
                    $selectedLabel = (string)($option['label'] ?? $selectedValue);
                    break;
                }
            }
            if (!destacamos_compare_values($selectedValue, $expectedValue, $fieldKey) && !destacamos_compare_values($selectedLabel, $expectedValue, $fieldKey)) {
                $mismatches[] = 'Valor no aplicado en ' . $fieldKey;
            }
            continue;
        }
        if (!destacamos_compare_values($currentValue, $expectedValue, $fieldKey)) {
            $mismatches[] = 'Valor no aplicado en ' . $fieldKey;
        }
    }

    return array(empty($mismatches), $mismatches);
}

function destacamos_mismatch_field_from_message($message) {
    $message = trim((string)$message);
    if ($message === '') return '';
    if (preg_match('/Valor no aplicado en\s+([a-z0-9_]+)/i', $message, $match)) {
        return destacamos_strtolower(trim((string)$match[1]));
    }
    if (preg_match('/Campo no encontrado tras guardar:\s*([a-z0-9_]+)/i', $message, $match)) {
        return destacamos_strtolower(trim((string)$match[1]));
    }
    return '';
}

function destacamos_is_soft_description_only_mismatch($mismatches) {
    $mismatches = array_values((array)$mismatches);
    if (empty($mismatches)) return false;
    foreach ($mismatches as $message) {
        $field = destacamos_mismatch_field_from_message($message);
        if ($field !== 'description') {
            return false;
        }
    }
    return true;
}

function destacamos_js_unescape_string($value) {
    $value = (string)$value;
    $value = str_replace(array("\r", "\n"), array('', ''), $value);
    return stripcslashes($value);
}

function destacamos_extract_js_string_option($html, $optionName) {
    $pattern = '/\b' . preg_quote($optionName, '/') . '\s*:\s*([\'"])(.*?)\1/s';
    if (preg_match($pattern, (string)$html, $match)) {
        return destacamos_js_unescape_string($match[2]);
    }
    return '';
}

function destacamos_extract_crt_photo_entries($crtPhotos) {
    $entries = array();
    $crtPhotos = destacamos_js_unescape_string($crtPhotos);
    if (preg_match_all('/var\s+a\s*=\s*(\{[^;]+\})\s*;/s', $crtPhotos, $matches)) {
        foreach ((array)($matches[1] ?? array()) as $jsonChunk) {
            $decoded = json_decode(destacamos_js_unescape_string($jsonChunk), true);
            $photo = is_array($decoded['img'] ?? null) ? $decoded['img'] : array();
            if (!empty($photo['name'])) {
                $entries[] = $photo;
            }
        }
    }
    return $entries;
}

function destacamos_extract_urls_from_js($text, $baseUrl) {
    $urls = array();
    if (preg_match_all('/(?:https?:\/\/|\/)[^\'"\s<>]+(?:\.php[^\'"\s<>]*)?/i', (string)$text, $matches)) {
        foreach ((array)($matches[0] ?? array()) as $url) {
            $resolved = destacamos_http_build_url($baseUrl, $url);
            $urls[$resolved] = $resolved;
        }
    }
    return array_values($urls);
}

function destacamos_extract_photo_page_meta($html, $baseUrl, $listingId) {
    $meta = array(
        'photo_page_url' => $baseUrl,
        'existing_count' => 0,
        'existing_photos' => array(),
        'upload_url' => '',
        'upload_input_name' => 'qqfile',
        'upload_fields' => array(),
        'title_field' => 'title',
        'warnings' => array(),
    );

    $action = destacamos_extract_js_string_option($html, 'action');
    if ($action !== '') {
        $meta['upload_url'] = destacamos_http_build_url($baseUrl, $action);
    } elseif (preg_match('/(?:action|endpoint)\s*:\s*[\'"]([^\'"]+)[\'"]/i', (string)$html, $match)) {
        $meta['upload_url'] = destacamos_http_build_url($baseUrl, $match[1]);
    }

    $titleField = destacamos_extract_js_string_option($html, 'title_field');
    if ($titleField !== '') {
        $meta['title_field'] = $titleField;
    }

    if (preg_match('/params\s*:\s*\{([^}]*)\}/is', (string)$html, $match)) {
        if (preg_match_all('/([A-Za-z0-9_]+)\s*:\s*(["\'])(.*?)\2/s', (string)$match[1], $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $meta['upload_fields'][(string)$pair[1]] = (string)$pair[3];
            }
        }
    }

    $crtPhotos = destacamos_extract_js_string_option($html, 'crt_photos');
    if ($crtPhotos !== '') {
        $meta['existing_photos'] = destacamos_extract_crt_photo_entries($crtPhotos);
        $meta['existing_count'] = count($meta['existing_photos']);
    }

    if ($meta['upload_url'] === '') {
        $meta['upload_url'] = $baseUrl;
    }
    return $meta;
}

function destacamos_photo_page_looks_valid($html, $listingId = '') {
    $html = (string)$html;
    $listingId = trim((string)$listingId);
    if ($html === '') {
        return false;
    }
    if (preg_match('/id\s*=\s*["\']file-uploader["\']/i', $html)) {
        return true;
    }
    if (preg_match('/qq\.FileUploader\s*\(/i', $html)) {
        return true;
    }
    if ($listingId !== '' && preg_match('/include\/upload\.php\?id=' . preg_quote($listingId, '/') . '/i', $html)) {
        return true;
    }
    return false;
}

function destacamos_open_photo_page(&$session, $listingId, $referer = '', &$debugLog = null) {
    $listingId = trim((string)$listingId);
    $candidates = array(
        'https://www.destacamos.net/edit_photos.php?id=' . rawurlencode($listingId),
        'https://www.destacamos.net/editphotos.php?id=' . rawurlencode($listingId),
        'https://www.destacamos.net/edit_images.php?id=' . rawurlencode($listingId),
        'https://www.destacamos.net/editimages.php?id=' . rawurlencode($listingId),
        'https://www.destacamos.net/photos.php?id=' . rawurlencode($listingId),
    );

    $seen = array();
    $lastResp = null;
    foreach ($candidates as $pageUrl) {
        if (isset($seen[$pageUrl])) continue;
        $seen[$pageUrl] = true;
        $page = destacamos_http_request($session, 'GET', $pageUrl, array(
            'referer' => $referer,
            'timeout' => 90,
        ));
        destacamos_debug_record_response($debugLog, 'photos_page_candidate', $page);
        $lastResp = $page;
        if (!$page['ok']) {
            continue;
        }
        if (destacamos_photo_page_looks_valid($page['body'], $listingId)) {
            return array(true, $page);
        }
    }

    return array(false, $lastResp ?: array(
        'ok' => false,
        'http_code' => 0,
        'body' => '',
        'effective_url' => '',
        'error' => 'No se encontró una pantalla de fotos válida.',
        'content_type' => '',
    ));
}

function destacamos_delete_existing_photos(&$session, $meta) {
    $deleted = 0;
    $warnings = array();
    foreach ((array)($meta['existing_photos'] ?? array()) as $photo) {
        $fileName = trim((string)($photo['name'] ?? ''));
        if ($fileName === '') {
            continue;
        }
        $resp = destacamos_http_request($session, 'POST', $meta['upload_url'], array(
            'data' => array(
                'deleteFile' => $fileName,
                'origName' => $fileName,
            ),
            'referer' => $meta['photo_page_url'] ?? '',
            'headers' => array('X-Requested-With: XMLHttpRequest'),
            'timeout' => 60,
        ));
        if ($resp['ok']) {
            $deleted++;
        } else {
            $warnings[] = 'No se pudo borrar la foto existente ' . $fileName . ': ' . ($resp['error'] !== '' ? $resp['error'] : ('HTTP ' . $resp['http_code']));
        }
    }
    return array($deleted, $warnings);
}

function destacamos_upload_single_photo(&$session, $meta, $listingId, $filePath, $currentTitle = '') {
    if (!is_file($filePath)) {
        return array(false, array('error' => 'No existe el archivo de imagen: ' . $filePath));
    }
    $binary = @file_get_contents($filePath);
    if ($binary === false) {
        return array(false, array('error' => 'No se pudo leer el archivo de imagen: ' . $filePath));
    }

    $query = array();
    foreach ((array)($meta['upload_fields'] ?? array()) as $key => $value) {
        $query[$key] = $value;
    }
    $query['qqfile'] = basename($filePath);
    $query['title'] = $currentTitle !== '' ? $currentTitle : 'undefined';
    $uploadUrl = destacamos_http_build_url($meta['upload_url'], '');
    $uploadUrl = strpos($uploadUrl, '?') === false ? ($uploadUrl . '?' . http_build_query($query)) : ($uploadUrl . '&' . http_build_query($query));

    $resp = destacamos_http_request($session, 'POST', $uploadUrl, array(
        'body' => $binary,
        'referer' => $meta['photo_page_url'] ?? '',
        'headers' => array(
            'X-Requested-With: XMLHttpRequest',
            'X-File-Name: ' . rawurlencode(basename($filePath)),
            'Content-Type: application/octet-stream',
        ),
        'timeout' => 180,
    ));
    if (!$resp['ok']) {
        return array(false, array('error' => 'Error subiendo imagen: ' . ($resp['error'] !== '' ? $resp['error'] : ('HTTP ' . $resp['http_code'])), 'response' => $resp));
    }

    $decoded = json_decode((string)$resp['body'], true);
    if (!is_array($decoded)) {
        return array(false, array('error' => 'La subida de fotos devolvió una respuesta no válida.', 'response' => $resp));
    }
    if (empty($decoded['success'])) {
        return array(false, array('error' => trim((string)($decoded['error'] ?? 'El servidor rechazó la imagen.')), 'response' => $decoded));
    }

    return array(true, array('response' => $decoded));
}

function destacamos_upload_listing_photos(&$session, $listingId, $photoPaths, $context = array(), &$debugLog = null) {
    list($okPage, $page) = destacamos_open_photo_page($session, $listingId, trim((string)($context['referer'] ?? '')), $debugLog);
    if ($okPage) {
        destacamos_debug_record_response($debugLog, 'photos_page', $page);
    }
    if (!$okPage || !$page['ok']) {
        return array(false, array('error' => 'No se pudo abrir la pantalla de fotos.', 'details' => $page));
    }
    $opts = destacamos_human_options($session);
    destacamos_human_pause($session, 'photos_page_read', (int)$opts['reading_min_ms'], (int)$opts['reading_max_ms']);

    $meta = destacamos_extract_photo_page_meta($page['body'], $page['effective_url'], $listingId);
    $meta['photo_page_url'] = $page['effective_url'];
    $photoPageUrl = trim((string)$meta['photo_page_url']);
    $currentTitle = trim((string)($context['title'] ?? ''));

    list($deletedCount, $deleteWarnings) = destacamos_delete_existing_photos($session, $meta);
    $meta['warnings'] = array_merge((array)$meta['warnings'], $deleteWarnings);

    if (($meta['existing_count'] ?? 0) > 0) {
        $verifyDelete = destacamos_http_request($session, 'GET', $photoPageUrl, array(
            'referer' => $page['effective_url'],
            'timeout' => 90,
        ));
        destacamos_debug_record_response($debugLog, 'photos_verify_delete', $verifyDelete);
        if ($verifyDelete['ok']) {
            $afterDelete = destacamos_extract_photo_page_meta($verifyDelete['body'], $verifyDelete['effective_url'], $listingId);
            if (($afterDelete['existing_count'] ?? 0) > 0) {
                $verifyDeleteRetry = destacamos_http_request($session, 'GET', $photoPageUrl, array(
                    'referer' => $verifyDelete['effective_url'],
                    'timeout' => 90,
                ));
                destacamos_debug_record_response($debugLog, 'photos_verify_delete_retry', $verifyDeleteRetry);
                if ($verifyDeleteRetry['ok']) {
                    $afterDelete = destacamos_extract_photo_page_meta($verifyDeleteRetry['body'], $verifyDeleteRetry['effective_url'], $listingId);
                    $meta = array_merge($meta, array(
                        'upload_url' => $afterDelete['upload_url'] ?? ($meta['upload_url'] ?? ''),
                        'title_field_name' => $afterDelete['title_field_name'] ?? ($meta['title_field_name'] ?? ''),
                    ));
                }
            }
            if (($afterDelete['existing_count'] ?? 0) > 0) {
                return array(false, array(
                    'error' => 'No se pudieron borrar todas las fotos anteriores del anuncio.',
                    'photosDeleted' => $deletedCount,
                    'photosUploaded' => 0,
                    'warnings' => $meta['warnings'],
                ));
            }
        }
    }

    $uploadedCount = 0;
    foreach ((array)$photoPaths as $photoPath) {
        destacamos_human_pause($session, 'before_photo_upload_' . ($uploadedCount + 1), (int)$opts['photo_between_min_ms'], (int)$opts['photo_between_max_ms']);
        list($okUpload, $uploadMeta) = destacamos_upload_single_photo($session, $meta, $listingId, $photoPath, $currentTitle);
        if (!$okUpload) {
            return array(false, array(
                'error' => $uploadMeta['error'] ?? 'No se pudo subir una imagen.',
                'photosDeleted' => $deletedCount,
                'photosUploaded' => $uploadedCount,
                'warnings' => $meta['warnings'],
            ));
        }
        $uploadedCount++;
    }

    $verifyPage = destacamos_http_request($session, 'GET', $photoPageUrl, array(
        'referer' => $page['effective_url'],
        'timeout' => 90,
    ));
    destacamos_debug_record_response($debugLog, 'photos_verify_upload', $verifyPage);
    if ($verifyPage['ok']) {
        $afterUpload = destacamos_extract_photo_page_meta($verifyPage['body'], $verifyPage['effective_url'], $listingId);
        if (($afterUpload['existing_count'] ?? 0) < $uploadedCount) {
            $verifyUploadRetry = destacamos_http_request($session, 'GET', $photoPageUrl, array(
                'referer' => $verifyPage['effective_url'],
                'timeout' => 90,
            ));
            destacamos_debug_record_response($debugLog, 'photos_verify_upload_retry', $verifyUploadRetry);
            if ($verifyUploadRetry['ok']) {
                $afterUpload = destacamos_extract_photo_page_meta($verifyUploadRetry['body'], $verifyUploadRetry['effective_url'], $listingId);
            }
        }
        if (($afterUpload['existing_count'] ?? 0) < $uploadedCount) {
            return array(false, array(
                'error' => 'La web no confirmó todas las fotos subidas.',
                'photosDeleted' => $deletedCount,
                'photosUploaded' => $uploadedCount,
                'warnings' => $meta['warnings'],
            ));
        }
    }

    return array(true, array(
        'photosPageOk' => true,
        'photosDeleted' => $deletedCount,
        'photosUploaded' => $uploadedCount,
        'warnings' => array_values(array_unique(array_filter((array)$meta['warnings']))),
    ));
}

function ejecutarAutomatizacion(array $payload): array
{
    $username = trim((string)($payload['username'] ?? ''));
    $password = trim((string)($payload['password'] ?? ''));
    $listingId = trim((string)($payload['listingId'] ?? ''));
    $timeoutMs = max(30000, (int)($payload['timeoutMs'] ?? 90000));
    $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : array();
    $editPhotos = !empty($payload['editPhotos']);
    $photoPaths = array_values(array_filter(array_map('strval', (array)($payload['photos'] ?? array()))));

    $result = array(
        'ok' => false,
        'loginOk' => false,
        'editPageOk' => false,
        'saveAttempted' => !empty($fields),
        'saveClicked' => false,
        'touchedFields' => array(),
        'photosPageOk' => false,
        'photosDeleted' => 0,
        'photosUploaded' => 0,
        'warnings' => array(),
        'currentUrl' => null,
    );

    if ($username === '' || $password === '' || $listingId === '') {
        $result['error'] = 'Faltan username, password o listingId';
        return $result;
    }

    $externalSession = is_array($payload['session'] ?? null) && !empty($payload['session']['cookie_file']);
    $session = $externalSession ? $payload['session'] : destacamos_http_session((int)ceil($timeoutMs / 1000));
    $session['timeout'] = max(15, (int)ceil($timeoutMs / 1000));
    $session['human'] = destacamos_payload_human_settings($payload);
    $debugLog = array(
        'enabled' => !empty($payload['debug_log']),
        'steps' => array(),
        'context' => array(
            'listing_id' => $listingId,
            'external_session' => $externalSession,
        ),
    );

    try {
        $currentPhase = 'init';
        $defaultEditUrl = 'https://www.destacamos.net/editad.php?id=' . rawurlencode($listingId);
        $loginMeta = array();
        $saveValidationErrors = array();
        $usedExistingSession = false;
        if ($externalSession) {
            list($sessionOk, $sessionMeta) = destacamos_session_is_authenticated($session, $username);
            if ($sessionOk) {
                $usedExistingSession = true;
                $loginMeta = array(
                    'login_url' => trim((string)($sessionMeta['current_url'] ?? '')),
                    'session_reused' => true,
                );
            }
        }
        if (!$usedExistingSession) {
            $currentPhase = 'login';
            list($okLogin, $loginMeta) = destacamos_login_session($session, $username, $password, $debugLog);
            if (!$okLogin) {
                $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado'));
                $result['currentUrl'] = trim((string)($loginMeta['current_url'] ?? ''));
                $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
                if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
                return $result;
            }
        }

        if (empty($loginMeta['login_url'])) {
            $loginMeta['login_url'] = 'https://www.destacamos.net/browse_listings.php';
        }
        if (!empty($usedExistingSession)) {
            $result['sessionReused'] = true;
        }
        if (empty($usedExistingSession) && empty($result['sessionReused'])) {
            $result['sessionReused'] = false;
        }

        if (empty($loginMeta['login_url']) && empty($usedExistingSession)) {
            $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado'));
            $result['currentUrl'] = trim((string)($loginMeta['current_url'] ?? ''));
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            return $result;
        }
        $result['loginOk'] = true;
        $result['currentUrl'] = trim((string)($loginMeta['login_url'] ?? ''));

        if (!empty($fields)) {
            $opts = destacamos_human_options($session);
            destacamos_human_pause($session, 'post_login_navigation_pause', (int)$opts['default_min_ms'], (int)$opts['default_max_ms']);
            $editUrl = $defaultEditUrl;
            $currentPhase = 'edit_fetch';
            $editPage = destacamos_http_request($session, 'GET', $editUrl, array('timeout' => max(60, (int)ceil($timeoutMs / 1000))));
            destacamos_debug_record_response($debugLog, 'edit_page', $editPage);
            if (!$editPage['ok']) {
                throw new RuntimeException('No se pudo abrir la pantalla de edición del anuncio.');
            }
            $result['currentUrl'] = $editPage['effective_url'];

            $form = destacamos_extract_form($editPage['body'], $editPage['effective_url'], array(
                'form_id' => 'formularioeditar',
                'button_ids' => array('guardarcambios'),
                'input_ids' => array('title', 'description', 'telefono', 'city', 'localidad'),
            ));
            if (!$form) {
                throw new RuntimeException('No se encontró el formulario de edición del anuncio.');
            }
            $result['editPageOk'] = true;
            destacamos_human_pause($session, 'edit_page_read', (int)$opts['reading_min_ms'], (int)$opts['reading_max_ms']);

            $originalFieldSnapshot = destacamos_capture_edit_form_values($form, array('telefono', 'zip', 'city', 'localidad'));
            $fieldKeysToApply = array(
                'title',
                'description',
                'nombre',
                'telefono',
                'whatsapp',
                'telegram',
                'localidad',
                'city',
                'zip',
                'edad',
                'idiomas',
                'dias_trabajo',
                'horario_de_trabajo',
                'color_de_pelo',
                'altura',
                'peso',
                'profesion',
                'pais_de_origen',
            );
            $expected = array();
            foreach ($fieldKeysToApply as $fieldKey) {
                if (!array_key_exists($fieldKey, $fields)) continue;
                $fallbackValue = array_key_exists($fieldKey, $originalFieldSnapshot) ? $originalFieldSnapshot[$fieldKey] : null;
                $desiredValue = $fields[$fieldKey];
                $found = destacamos_apply_edit_field($form, $fieldKey, $desiredValue, $fallbackValue);
                if (!$found) {
                    throw new RuntimeException('Campo editable no encontrado en la ficha: ' . $fieldKey);
                }
                list($expectedFound, $effectiveValue) = destacamos_form_read_value($form, $fieldKey);
                if (!$expectedFound) {
                    $effectiveValue = $desiredValue;
                }
                $expected[$fieldKey] = $effectiveValue;
                $result['touchedFields'][$fieldKey] = $effectiveValue;
            }
            $result['originalProtectedFields'] = $originalFieldSnapshot;
            destacamos_form_pick_submit($form, array('guardarcambios'), array('guardar', 'cambios'));
            destacamos_human_typing_pause($session, 'edit_typing', array_values($expected));
            destacamos_human_pause($session, 'save_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);

            $saveRequestOptions = array(
                'data' => $form['data'],
                'multipart' => stripos((string)($form['enctype'] ?? ''), 'multipart/form-data') !== false,
                'referer' => $editPage['effective_url'],
                'headers' => array(
                    'Origin: https://www.destacamos.net',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ),
                'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
            );
            $saveResp = destacamos_http_request($session, $form['method'], $form['action_url'], $saveRequestOptions);
            $currentPhase = 'save_submit';
            destacamos_debug_record_response($debugLog, 'edit_submit', $saveResp);
            if (!$saveResp['ok']) {
                throw new RuntimeException('No se pudo guardar el anuncio editado.');
            }
            $result['saveClicked'] = true;
            $result['currentUrl'] = $saveResp['effective_url'];
            $saveValidationErrors = destacamos_extract_edit_error_messages($saveResp['body']);
            if (!empty($saveValidationErrors) && preg_match('~editad\.php~i', (string)$saveResp['effective_url']) && destacamos_messages_include_phone_or_zip_error($saveValidationErrors)) {
                $retryEditPage = destacamos_http_request($session, 'GET', $editUrl, array(
                    'referer' => $saveResp['effective_url'],
                    'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
                ));
                destacamos_debug_record_response($debugLog, 'edit_retry_fetch_after_phone_zip_error', $retryEditPage);
                if ($retryEditPage['ok']) {
                    $retryForm = destacamos_extract_form($retryEditPage['body'], $retryEditPage['effective_url'], array(
                        'form_id' => 'formularioeditar',
                        'button_ids' => array('guardarcambios'),
                        'input_ids' => array('title', 'description', 'telefono', 'city', 'localidad'),
                    ));
                    if ($retryForm) {
                        $retrySnapshot = destacamos_capture_edit_form_values($retryForm, array('telefono', 'zip', 'city', 'localidad'));
                        $retryExpected = array();
                        foreach ($fieldKeysToApply as $fieldKey) {
                            if (!array_key_exists($fieldKey, $fields)) continue;
                            $fallbackValue = array_key_exists($fieldKey, $retrySnapshot) ? $retrySnapshot[$fieldKey] : (array_key_exists($fieldKey, $originalFieldSnapshot) ? $originalFieldSnapshot[$fieldKey] : null);
                            $desiredValue = $fields[$fieldKey];
                            if (in_array($fieldKey, array('telefono', 'zip', 'city', 'localidad'), true)) {
                                $desiredValue = $fallbackValue;
                            }
                            $found = destacamos_apply_edit_field($retryForm, $fieldKey, $desiredValue, $fallbackValue);
                            if (!$found) {
                                throw new RuntimeException('Campo editable no encontrado en la ficha: ' . $fieldKey);
                            }
                            list($expectedFound, $effectiveValue) = destacamos_form_read_value($retryForm, $fieldKey);
                            if (!$expectedFound) {
                                $effectiveValue = $desiredValue;
                            }
                            $retryExpected[$fieldKey] = $effectiveValue;
                        }
                        destacamos_form_pick_submit($retryForm, array('guardarcambios'), array('guardar', 'cambios'));
                        destacamos_human_pause($session, 'retry_save_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);
                        $retrySaveResp = destacamos_http_request($session, $retryForm['method'], $retryForm['action_url'], array(
                            'data' => $retryForm['data'],
                            'multipart' => stripos((string)($retryForm['enctype'] ?? ''), 'multipart/form-data') !== false,
                            'referer' => $retryEditPage['effective_url'],
                            'headers' => array(
                                'Origin: https://www.destacamos.net',
                                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            ),
                            'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
                        ));
                        destacamos_debug_record_response($debugLog, 'edit_submit_retry_keep_original_phone_zip', $retrySaveResp);
                        if ($retrySaveResp['ok']) {
                            $retryValidationErrors = destacamos_extract_edit_error_messages($retrySaveResp['body']);
                            $result['retry_keep_original_contact_location'] = array(
                                'attempted' => true,
                                'triggered_by' => $saveValidationErrors,
                                'protected_fields' => $retrySnapshot,
                                'validation_errors_after_retry' => $retryValidationErrors,
                            );
                            if (empty($retryValidationErrors) || !preg_match('~editad\.php~i', (string)$retrySaveResp['effective_url'])) {
                                $saveResp = $retrySaveResp;
                                $saveValidationErrors = array();
                                $expected = $retryExpected;
                                $result['touchedFields'] = $retryExpected;
                                $result['currentUrl'] = $retrySaveResp['effective_url'];
                            } else {
                                $saveValidationErrors = $retryValidationErrors;
                                $result['currentUrl'] = $retrySaveResp['effective_url'];
                            }
                        }
                    }
                }
            }
            if (!empty($saveValidationErrors) && preg_match('~editad\.php~i', (string)$saveResp['effective_url'])) {
                $result['validation_errors'] = $saveValidationErrors;
                $result['error_code'] = destacamos_edit_error_code($saveValidationErrors);
                throw new RuntimeException('La web rechazó el guardado: ' . implode(' ', $saveValidationErrors));
            }
            destacamos_human_pause($session, 'post_save_wait', (int)$opts['default_min_ms'], (int)$opts['default_max_ms']);

            $confirmResp = destacamos_http_request($session, 'GET', $editUrl, array(
                'referer' => $saveResp['effective_url'],
                'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
            ));
            $currentPhase = 'save_confirm';
            destacamos_debug_record_response($debugLog, 'edit_confirm', $confirmResp);
            if (!$confirmResp['ok']) {
                throw new RuntimeException('No se pudo reabrir la ficha para validar los cambios guardados.');
            }
            $result['currentUrl'] = $confirmResp['effective_url'];

            list($okValidate, $mismatches) = destacamos_validate_saved_fields($confirmResp['body'], $confirmResp['effective_url'], $expected);
            if (!$okValidate) {
                $confirmValidationErrors = destacamos_extract_edit_error_messages($confirmResp['body']);
                $allValidationErrors = array_values(array_unique(array_merge($saveValidationErrors, $confirmValidationErrors)));
                if (!empty($allValidationErrors) && destacamos_messages_include_phone_or_zip_error($allValidationErrors)) {
                    $confirmRetryForm = destacamos_extract_form($confirmResp['body'], $confirmResp['effective_url'], array(
                        'form_id' => 'formularioeditar',
                        'button_ids' => array('guardarcambios'),
                        'input_ids' => array('title', 'description', 'telefono', 'city', 'localidad'),
                    ));
                    if ($confirmRetryForm) {
                        $confirmRetrySnapshot = destacamos_capture_edit_form_values($confirmRetryForm, array('telefono', 'zip', 'city', 'localidad'));
                        $confirmRetryExpected = array();
                        foreach ($fieldKeysToApply as $fieldKey) {
                            if (!array_key_exists($fieldKey, $fields)) continue;
                            $fallbackValue = array_key_exists($fieldKey, $confirmRetrySnapshot)
                                ? $confirmRetrySnapshot[$fieldKey]
                                : (array_key_exists($fieldKey, $originalFieldSnapshot) ? $originalFieldSnapshot[$fieldKey] : null);
                            $desiredValue = $fields[$fieldKey];
                            if (in_array($fieldKey, array('telefono', 'zip', 'city', 'localidad'), true)) {
                                $desiredValue = $fallbackValue;
                            }
                            $found = destacamos_apply_edit_field($confirmRetryForm, $fieldKey, $desiredValue, $fallbackValue);
                            if (!$found) {
                                throw new RuntimeException('Campo editable no encontrado en la ficha: ' . $fieldKey);
                            }
                            list($expectedFound, $effectiveValue) = destacamos_form_read_value($confirmRetryForm, $fieldKey);
                            if (!$expectedFound) {
                                $effectiveValue = $desiredValue;
                            }
                            $confirmRetryExpected[$fieldKey] = $effectiveValue;
                        }
                        destacamos_form_pick_submit($confirmRetryForm, array('guardarcambios'), array('guardar', 'cambios'));
                        destacamos_human_pause($session, 'confirm_retry_save_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);
                        $confirmRetrySaveResp = destacamos_http_request($session, $confirmRetryForm['method'], $confirmRetryForm['action_url'], array(
                            'data' => $confirmRetryForm['data'],
                            'multipart' => stripos((string)($confirmRetryForm['enctype'] ?? ''), 'multipart/form-data') !== false,
                            'referer' => $confirmResp['effective_url'],
                            'headers' => array(
                                'Origin: https://www.destacamos.net',
                                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            ),
                            'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
                        ));
                        destacamos_debug_record_response($debugLog, 'edit_submit_retry_after_confirm_phone_zip', $confirmRetrySaveResp);
                        if ($confirmRetrySaveResp['ok']) {
                            $confirmRetryValidationErrors = destacamos_extract_edit_error_messages($confirmRetrySaveResp['body']);
                            $result['retry_keep_original_contact_location_after_confirm'] = array(
                                'attempted' => true,
                                'triggered_by' => $allValidationErrors,
                                'protected_fields' => $confirmRetrySnapshot,
                                'validation_errors_after_retry' => $confirmRetryValidationErrors,
                            );
                            if (empty($confirmRetryValidationErrors) || !preg_match('~editad\.php~i', (string)$confirmRetrySaveResp['effective_url'])) {
                                $secondConfirmResp = destacamos_http_request($session, 'GET', $editUrl, array(
                                    'referer' => $confirmRetrySaveResp['effective_url'],
                                    'timeout' => max(60, (int)ceil($timeoutMs / 1000)),
                                ));
                                destacamos_debug_record_response($debugLog, 'edit_confirm_after_retry_after_confirm_phone_zip', $secondConfirmResp);
                                if ($secondConfirmResp['ok']) {
                                    list($secondOkValidate, $secondMismatches) = destacamos_validate_saved_fields($secondConfirmResp['body'], $secondConfirmResp['effective_url'], $confirmRetryExpected);
                                    $secondConfirmValidationErrors = destacamos_extract_edit_error_messages($secondConfirmResp['body']);
                                    if ($secondOkValidate && empty($secondConfirmValidationErrors)) {
                                        $confirmResp = $secondConfirmResp;
                                        $expected = $confirmRetryExpected;
                                        $result['touchedFields'] = $confirmRetryExpected;
                                        $result['currentUrl'] = $secondConfirmResp['effective_url'];
                                        $okValidate = true;
                                        $mismatches = array();
                                        $allValidationErrors = array();
                                    } else {
                                        $allValidationErrors = array_values(array_unique(array_merge($confirmRetryValidationErrors, $secondConfirmValidationErrors)));
                                        if (empty($allValidationErrors) && !empty($secondMismatches)) {
                                            $mismatches = $secondMismatches;
                                        }
                                    }
                                }
                            } else {
                                $allValidationErrors = $confirmRetryValidationErrors;
                            }
                        }
                    }
                }
                if (!$okValidate) {
                    if (!empty($allValidationErrors)) {
                        $result['validation_errors'] = $allValidationErrors;
                        $result['error_code'] = destacamos_edit_error_code($allValidationErrors);
                        throw new RuntimeException('La web rechazó el guardado: ' . implode(' ', $allValidationErrors));
                    }
                    if (destacamos_is_soft_description_only_mismatch($mismatches)) {
                        $expectedDescription = (string)($expected['description'] ?? '');
                        $expectedLength = function_exists('mb_strlen') ? mb_strlen($expectedDescription) : strlen($expectedDescription);
                        $result['warnings'][] = 'Guardado confirmado con normalización externa en description.';
                        $result['save_soft_mismatch'] = array(
                            'field' => 'description',
                            'mismatches' => array_values($mismatches),
                            'expected_length' => $expectedLength,
                            'mismatch_count' => count((array)$mismatches),
                            'accepted_as' => 'soft_warning',
                            'phase' => $currentPhase,
                        );
                        $okValidate = true;
                        $mismatches = array();
                    }
                }
                if (!$okValidate) {
                    throw new RuntimeException('La web devolvió la ficha, pero no confirmó todos los cambios: ' . implode(' ', $mismatches));
                }
            }
        }

        if ($editPhotos) {
            $currentPhase = 'photo_upload';
            list($okPhotos, $photoMeta) = destacamos_upload_listing_photos($session, $listingId, $photoPaths, array(
                'title' => trim((string)($fields['title'] ?? '')),
                'referer' => $result['currentUrl'] ?? $defaultEditUrl,
            ), $debugLog);
            $result['photosPageOk'] = !empty($photoMeta['photosPageOk']);
            $result['photosDeleted'] = (int)($photoMeta['photosDeleted'] ?? 0);
            $result['photosUploaded'] = (int)($photoMeta['photosUploaded'] ?? 0);
            $result['warnings'] = array_values(array_unique(array_filter(array_merge((array)$result['warnings'], (array)($photoMeta['warnings'] ?? array())))));
            if (!$okPhotos) {
                throw new RuntimeException(trim((string)($photoMeta['error'] ?? 'No se pudieron actualizar las fotos del anuncio.')));
            }
        }

        $result['ok'] = true;
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['error'] = $e->getMessage();
        $classification = destacamos_classify_runtime_error($result['error'], $currentPhase ?? 'unknown');
        if (trim((string)($result['error_code'] ?? '')) === '') {
            $result['error_code'] = $classification['error_code'];
        }
        $result['error_category'] = $classification['error_category'];
        $result['error_phase'] = $classification['error_phase'];
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } finally {
        if (!$externalSession) {
            destacamos_http_cleanup_session($session);
        }
    }
}

function destacamos_free_bump_parse_opacity($style) {
    $style = trim((string)$style);
    if ($style === '') return null;
    if (preg_match('/opacity\s*:\s*([0-9]+(?:\.[0-9]+)?)/i', $style, $match)) {
        return (float)$match[1];
    }
    return null;
}

function destacamos_free_bump_is_disabled_link($href, $style) {
    $href = trim((string)$href);
    $style = trim((string)$style);
    if ($href === '' || stripos($href, 'javascript:') === 0) {
        return true;
    }
    $opacity = destacamos_free_bump_parse_opacity($style);
    if ($opacity !== null && $opacity <= 0.5) {
        return true;
    }
    return false;
}

function destacamos_free_bump_has_disabled_class($className) {
    $className = destacamos_strtolower(trim((string)$className));
    if ($className === '') return false;
    foreach (array('disabled', 'disable', 'agotado', 'inactive', 'inactivo', 'bloqueado') as $needle) {
        if (destacamos_strpos($className, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function destacamos_free_bump_collect_context_style($node) {
    $styles = array();
    $current = $node;
    $depth = 0;
    while ($current instanceof DOMElement && $depth < 3) {
        $style = trim((string)destacamos_node_attr($current, 'style'));
        if ($style !== '') {
            $styles[] = $style;
        }
        $current = $current->parentNode instanceof DOMElement ? $current->parentNode : null;
        $depth++;
    }
    return trim(implode(' ', $styles));
}

function destacamos_free_bump_collect_context_class($node) {
    $classes = array();
    $current = $node;
    $depth = 0;
    while ($current instanceof DOMElement && $depth < 3) {
        $className = trim((string)destacamos_node_attr($current, 'class'));
        if ($className !== '') {
            $classes[] = $className;
        }
        $current = $current->parentNode instanceof DOMElement ? $current->parentNode : null;
        $depth++;
    }
    return trim(implode(' ', $classes));
}

function destacamos_extract_global_free_bump_count($html) {
    $html = (string)$html;
    if (preg_match('/Subir\s+GRATIS\s+estos\s+(\d+)\s+perfiles/iu', $html, $match)) {
        return max(0, (int)($match[1] ?? 0));
    }
    if (preg_match('/renewad\.php\?subir-todos=1/iu', $html)) {
        return 1;
    }
    return 0;
}

function destacamos_extract_free_bump_row_from_chunk($listingId, $chunk, $baseUrl) {
    $listingId = preg_replace('/\D+/', '', (string)$listingId);
    $chunk = (string)$chunk;
    if ($listingId === '' || $chunk === '') {
        return array();
    }

    $title = '';
    $detailUrl = '';
    if (preg_match('~<h3\b[^>]*>.*?<a\b[^>]*href=(["\'])([^"\']+)\1[^>]*>(.*?)</a>.*?</h3>~is', $chunk, $titleMatch)) {
        $title = destacamos_html_normalize(strip_tags((string)($titleMatch[3] ?? '')));
        $detailUrl = destacamos_http_build_url($baseUrl, (string)($titleMatch[2] ?? ''));
    }

    $phone = '';
    if (preg_match_all('/(?:\+34\s*)?([6789]\d{8})/', $chunk, $phoneMatches) && !empty($phoneMatches[1])) {
        $phone = trim((string)$phoneMatches[1][0]);
    }

    $renewUrl = '';
    $renewText = '';
    $renewStyle = '';
    $renewClass = '';
    $renewOpacity = null;
    $hrefRaw = '';

    $patterns = array(
        '~<span\b([^>]*)class=(["\'])([^"\']*accion-subir-gratis[^"\']*)\2([^>]*)>.*?<a\b([^>]*)href=(["\'])([^"\']*renewad\.php\?id=' . preg_quote($listingId, '~') . '[^"\']*)\6([^>]*)>(.*?)</a>~is',
        '~<a\b([^>]*)href=(["\'])([^"\']*renewad\.php\?id=' . preg_quote($listingId, '~') . '[^"\']*)\2([^>]*)>(.*?)</a>~is',
    );

    foreach ($patterns as $index => $pattern) {
        if (!preg_match($pattern, $chunk, $match)) {
            continue;
        }

        if ($index === 0) {
            $spanAttrs = destacamos_parse_html_attributes(trim((string)($match[1] ?? '')) . ' ' . trim((string)($match[4] ?? '')));
            $anchorAttrs = destacamos_parse_html_attributes(trim((string)($match[5] ?? '')) . ' ' . trim((string)($match[8] ?? '')));
            $hrefRaw = (string)($match[7] ?? '');
            $renewText = destacamos_html_normalize(strip_tags((string)($match[9] ?? '')));
            $renewStyle = trim((string)($anchorAttrs['style'] ?? ''));
            if ($renewStyle === '' && !empty($spanAttrs['style'])) {
                $renewStyle = trim((string)$spanAttrs['style']);
            }
            $renewClass = trim((string)($anchorAttrs['class'] ?? ''));
            if ($renewClass === '' && !empty($spanAttrs['class'])) {
                $renewClass = trim((string)$spanAttrs['class']);
            }
        } else {
            $anchorAttrs = destacamos_parse_html_attributes(trim((string)($match[1] ?? '')) . ' ' . trim((string)($match[4] ?? '')));
            $hrefRaw = (string)($match[3] ?? '');
            $renewText = destacamos_html_normalize(strip_tags((string)($match[5] ?? '')));
            $renewStyle = trim((string)($anchorAttrs['style'] ?? ''));
            $renewClass = trim((string)($anchorAttrs['class'] ?? ''));
            if ($renewClass === '' && stripos($chunk, 'accion-subir-gratis') !== false) {
                $renewClass = 'accion-subir-gratis';
            }
        }

        break;
    }

    if ($hrefRaw !== '') {
        $renewUrl = destacamos_http_build_url($baseUrl, $hrefRaw);
        $renewOpacity = destacamos_free_bump_parse_opacity($renewStyle);
    }

    return array(
        'listing_id' => $listingId,
        'title' => $title,
        'phone' => $phone,
        'detail_url' => $detailUrl,
        'renew_url' => $renewUrl,
        'renew_text' => $renewText,
        'renew_style' => $renewStyle,
        'renew_class' => $renewClass,
        'renew_opacity' => $renewOpacity,
        'free_bump_available' => ($hrefRaw !== '')
            && !destacamos_free_bump_is_disabled_link($hrefRaw, $renewStyle)
            && !destacamos_free_bump_has_disabled_class($renewClass),
    );
}

function destacamos_extract_free_bump_rows_fallback($html, $baseUrl) {
    $html = (string)$html;
    $rowsOut = array();
    if ($html === '') {
        return $rowsOut;
    }

    if (preg_match_all('~<tr\b[^>]*id=(["\'])p(\d+)\1[^>]*>~is', $html, $rowMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $count = count($rowMatches);
        for ($i = 0; $i < $count; $i++) {
            $listingId = preg_replace('/\D+/', '', (string)($rowMatches[$i][2][0] ?? ''));
            $start = (int)($rowMatches[$i][0][1] ?? 0);
            $end = ($i + 1 < $count) ? (int)($rowMatches[$i + 1][0][1] ?? strlen($html)) : strlen($html);
            $chunk = substr($html, $start, max(0, $end - $start));
            $row = destacamos_extract_free_bump_row_from_chunk($listingId, $chunk, $baseUrl);
            if (!empty($row)) {
                $rowsOut[$listingId] = $row;
            }
        }
        if (!empty($rowsOut)) {
            return array_values($rowsOut);
        }
    }

    if (!preg_match_all('~<a\b([^>]*)href=(["\'])([^"\']*renewad\.php\?id=(\d+)[^"\']*)\2([^>]*)>(.*?)</a>~is', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return $rowsOut;
    }

    foreach ($matches as $match) {
        $listingId = preg_replace('/\D+/', '', (string)($match[4][0] ?? ''));
        if ($listingId === '' || isset($rowsOut[$listingId])) {
            continue;
        }

        $anchorHtml = (string)($match[0][0] ?? '');
        $anchorPos = (int)($match[0][1] ?? 0);
        $snippetStart = max(0, $anchorPos - 3500);
        $snippetLen = max(strlen($anchorHtml) + 7000, 7000);
        $snippet = substr($html, $snippetStart, $snippetLen);
        $row = destacamos_extract_free_bump_row_from_chunk($listingId, $snippet, $baseUrl);
        if (!empty($row)) {
            $rowsOut[$listingId] = $row;
        }
    }

    return array_values($rowsOut);
}

function destacamos_extract_free_bump_rows($html, $baseUrl) {
    $rowsOut = array();
    list($dom, $xpath) = destacamos_html_load_xpath($html);
    if (!$dom || !$xpath) {
        return destacamos_extract_free_bump_rows_fallback($html, $baseUrl);
    }

    $rows = $xpath->query('//tr[starts-with(@id, "p")]');
    if (!$rows) {
        return destacamos_extract_free_bump_rows_fallback($html, $baseUrl);
    }

    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) continue;
        $listingId = trim((string)preg_replace('/^p/', '', destacamos_node_attr($row, 'id')));
        if ($listingId === '') continue;

        $title = '';
        $detailUrl = '';
        $titleNodes = $xpath->query('.//h3//a[1]', $row);
        if ($titleNodes && $titleNodes->length > 0 && $titleNodes->item(0) instanceof DOMElement) {
            $title = destacamos_html_normalize($titleNodes->item(0)->textContent);
            $detailUrl = destacamos_http_build_url($baseUrl, destacamos_node_attr($titleNodes->item(0), 'href'));
        }

        $phone = '';
        $strongs = $xpath->query('.//table[contains(@class, "tabla-detalles-anuncios")]//strong', $row);
        if ($strongs) {
            foreach ($strongs as $strong) {
                $digits = destacamos_phone_digits($strong->textContent);
                if ($digits !== '' && strlen($digits) >= 9) {
                    $phone = $digits;
                    break;
                }
            }
        }

        $renewUrl = '';
        $renewText = '';
        $renewStyle = '';
        $renewOpacity = null;
        $freeAvailable = false;
        $renewClass = '';
        $linkQueries = array(
            './/span[' . destacamos_xpath_has_class('accion-subir-gratis') . ']//a[contains(@href, "renewad.php")]',
            './/a[contains(@href, "renewad.php?id=")]',
        );
        $link = null;
        foreach ($linkQueries as $query) {
            $linkNodes = $xpath->query($query, $row);
            if ($linkNodes && $linkNodes->length > 0 && $linkNodes->item(0) instanceof DOMElement) {
                $link = $linkNodes->item(0);
                break;
            }
        }
        if ($link instanceof DOMElement) {
            $href = trim((string)destacamos_node_attr($link, 'href'));
            $renewText = destacamos_html_normalize($link->textContent);
            $renewStyle = destacamos_free_bump_collect_context_style($link);
            $renewClass = destacamos_free_bump_collect_context_class($link);
            $renewOpacity = destacamos_free_bump_parse_opacity($renewStyle);
            if ($href !== '') {
                $renewUrl = destacamos_http_build_url($baseUrl, $href);
            }
            $freeAvailable = !destacamos_free_bump_is_disabled_link($href, $renewStyle)
                && !destacamos_free_bump_has_disabled_class($renewClass);
        }

        $rowsOut[] = array(
            'listing_id' => $listingId,
            'title' => $title,
            'phone' => $phone,
            'detail_url' => $detailUrl,
            'renew_url' => $renewUrl,
            'renew_text' => $renewText,
            'renew_style' => $renewStyle,
            'renew_class' => $renewClass,
            'renew_opacity' => $renewOpacity,
            'free_bump_available' => $freeAvailable,
        );
    }

    $rowsOut = array_values($rowsOut);
    $globalHint = destacamos_extract_global_free_bump_count($html);
    $hasAvailable = false;
    foreach ($rowsOut as $row) {
        if (!empty($row['free_bump_available'])) {
            $hasAvailable = true;
            break;
        }
    }

    if (empty($rowsOut) || ($globalHint > 0 && !$hasAvailable)) {
        $fallbackRows = destacamos_extract_free_bump_rows_fallback($html, $baseUrl);
        if (!empty($fallbackRows)) {
            $merged = array();
            foreach ($rowsOut as $row) {
                $listingId = trim((string)($row['listing_id'] ?? ''));
                if ($listingId !== '') {
                    $merged[$listingId] = $row;
                }
            }
            foreach ($fallbackRows as $row) {
                $listingId = trim((string)($row['listing_id'] ?? ''));
                if ($listingId === '') {
                    continue;
                }
                if (!isset($merged[$listingId])) {
                    $merged[$listingId] = $row;
                    continue;
                }
                foreach (array('title', 'phone', 'detail_url', 'renew_url', 'renew_text', 'renew_style', 'renew_class', 'renew_opacity') as $field) {
                    if ((string)($merged[$listingId][$field] ?? '') === '' && (string)($row[$field] ?? '') !== '') {
                        $merged[$listingId][$field] = $row[$field];
                    }
                }
                if (empty($merged[$listingId]['free_bump_available']) && !empty($row['free_bump_available'])) {
                    $merged[$listingId]['free_bump_available'] = true;
                }
            }
            $rowsOut = array_values($merged);
        }
    }

    return $rowsOut;
}

function destacamos_pick_first_available_free_bump_row($rows, $allowedListingIds = array(), $telefono = '') {
    $allowedMap = array();
    foreach ((array)$allowedListingIds as $listingId) {
        $listingId = preg_replace('/\D+/', '', (string)$listingId);
        if ($listingId !== '') $allowedMap[$listingId] = true;
    }
    $targetPhone = destacamos_phone_digits($telefono);

    foreach ((array)$rows as $row) {
        $listingId = trim((string)($row['listing_id'] ?? ''));
        if (!empty($allowedMap) && !isset($allowedMap[$listingId])) continue;
        if (!$row['free_bump_available']) continue;
        if ($targetPhone !== '') {
            $digits = destacamos_phone_digits((string)($row['phone'] ?? ''));
            if ($digits === '' || substr($digits, -9) !== substr($targetPhone, -9)) continue;
        }
        return $row;
    }

    return null;
}

function destacamos_find_free_bump_candidate($html, $baseUrl, $telefono) {
    $rows = destacamos_extract_free_bump_rows($html, $baseUrl);
    $candidate = destacamos_pick_first_available_free_bump_row($rows, array(), $telefono);
    if (!$candidate) return null;
    return array(
        'renew_url' => trim((string)($candidate['renew_url'] ?? '')),
        'listing_id' => trim((string)($candidate['listing_id'] ?? '')),
    );
}

function destacamos_response_has_success_signal($html) {
    $signals = array('subido', 'renovado', 'actualizado', 'exito', 'éxito', 'correctamente');
    $haystack = destacamos_strtolower(destacamos_html_normalize($html));
    foreach ($signals as $signal) {
        if (destacamos_strpos($haystack, destacamos_strtolower($signal)) !== false) {
            return true;
        }
    }
    return false;
}

function destacamos_subir_gratis_disponible(array $payload): array
{
    $username = trim((string)($payload['username'] ?? ''));
    $password = trim((string)($payload['password'] ?? ''));
    $timeoutMs = max(30000, (int)($payload['timeoutMs'] ?? 45000));
    $allowedListingIds = array_values(array_filter(array_map(function($value) {
        return preg_replace('/\D+/', '', (string)$value);
    }, (array)($payload['allowed_listing_ids'] ?? array()))));

    $result = array(
        'ok' => false,
        'loginOk' => false,
        'listingFound' => false,
        'listingId' => null,
        'renewUrl' => null,
        'renewOk' => false,
        'currentUrl' => null,
        'error' => null,
        'error_code' => '',
        'available_count_before' => 0,
        'available_listing_ids_before' => array(),
        'checked_listing_ids_before' => array(),
        'before' => array(),
        'after' => array(),
    );

    if ($username === '' || $password === '') {
        $result['error'] = 'Faltan username o password';
        $result['error_code'] = 'missing_credentials';
        return $result;
    }

    $externalSession = is_array($payload['session'] ?? null) && !empty($payload['session']['cookie_file']);
    $session = $externalSession ? $payload['session'] : destacamos_http_session((int)ceil($timeoutMs / 1000));
    $session['timeout'] = max(15, (int)ceil($timeoutMs / 1000));
    $session['human'] = destacamos_payload_human_settings($payload);
    $debugLog = array(
        'enabled' => array_key_exists('debug_log', $payload) ? !empty($payload['debug_log']) : true,
        'steps' => array(),
        'context' => array(
            'mode' => 'free_bump_available',
            'allowed_listing_ids' => $allowedListingIds,
            'external_session' => $externalSession,
        ),
    );

    try {
        $loginMeta = array();
        $usedExistingSession = false;
        if ($externalSession) {
            list($sessionOk, $sessionMeta) = destacamos_session_is_authenticated($session, $username);
            if ($sessionOk) {
                $usedExistingSession = true;
                $loginMeta = array(
                    'login_url' => trim((string)($sessionMeta['current_url'] ?? '')),
                    'session_reused' => true,
                );
            }
        }
        if (!$usedExistingSession) {
            list($okLogin, $loginMeta) = destacamos_login_session($session, $username, $password, $debugLog);
            if (!$okLogin) {
                $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado'));
                $result['error_code'] = 'login_failed';
                $result['currentUrl'] = trim((string)($loginMeta['current_url'] ?? ''));
                $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
                if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
                return $result;
            }
        }

        $result['loginOk'] = true;
        $result['currentUrl'] = trim((string)($loginMeta['login_url'] ?? ''));
        $opts = destacamos_human_options($session);
        destacamos_human_pause($session, 'post_login_navigation_pause', (int)$opts['default_min_ms'], (int)$opts['default_max_ms']);

        $listUrl = 'https://www.destacamos.net/browse_listings.php';
        $listResp = destacamos_http_request($session, 'GET', $listUrl, array('timeout' => max(45, (int)ceil($timeoutMs / 1000))));
        destacamos_debug_record_response($debugLog, 'browse_listings_before', $listResp);
        if (!$listResp['ok']) {
            throw new RuntimeException('No se pudo abrir el listado de anuncios.');
        }
        $result['currentUrl'] = $listResp['effective_url'];
        destacamos_human_pause($session, 'browse_listings_read', (int)$opts['reading_min_ms'], (int)$opts['reading_max_ms']);

        $rows = destacamos_extract_free_bump_rows($listResp['body'], $listResp['effective_url']);
        $result['checked_listing_ids_before'] = array_values(array_map(function($row) {
            return trim((string)($row['listing_id'] ?? ''));
        }, $rows));
        $result['before'] = $rows;
        $result['available_global_count_hint'] = destacamos_extract_global_free_bump_count($listResp['body']);

        $availableRows = array_values(array_filter($rows, function($row) use ($allowedListingIds) {
            $listingId = trim((string)($row['listing_id'] ?? ''));
            if (!empty($allowedListingIds) && !in_array($listingId, $allowedListingIds, true)) {
                return false;
            }
            return !empty($row['free_bump_available']);
        }));
        $result['available_count_before'] = count($availableRows);
        $result['available_listing_ids_before'] = array_values(array_map(function($row) {
            return trim((string)($row['listing_id'] ?? ''));
        }, $availableRows));

        $candidate = destacamos_pick_first_available_free_bump_row($rows, $allowedListingIds);
        if (!$candidate) {
            $result['error'] = 'No hay ningún anuncio libre para subir gratis en esta cuenta.';
            $result['error_code'] = 'no_free_listing_available';
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
            return $result;
        }

        $result['listingFound'] = true;
        $result['listingId'] = trim((string)($candidate['listing_id'] ?? ''));
        $result['renewUrl'] = trim((string)($candidate['renew_url'] ?? ''));
        if ($result['renewUrl'] === '') {
            $result['error'] = 'No se pudo extraer la URL de renew del anuncio libre.';
            $result['error_code'] = 'missing_renew_url';
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
            return $result;
        }

        destacamos_human_pause($session, 'renew_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);
        $renewResp = destacamos_http_request($session, 'GET', $result['renewUrl'], array(
            'referer' => $listResp['effective_url'],
            'timeout' => max(45, (int)ceil($timeoutMs / 1000)),
        ));
        destacamos_debug_record_response($debugLog, 'renew_listing', $renewResp);
        if (!$renewResp['ok']) {
            throw new RuntimeException('No se pudo ejecutar la acción de "Subir gratis".');
        }

        $result['currentUrl'] = $renewResp['effective_url'];
        $listAfterResp = destacamos_http_request($session, 'GET', $listUrl, array(
            'referer' => $renewResp['effective_url'],
            'timeout' => max(45, (int)ceil($timeoutMs / 1000)),
        ));
        destacamos_debug_record_response($debugLog, 'browse_listings_after', $listAfterResp);
        if ($listAfterResp['ok']) {
            $result['after'] = destacamos_extract_free_bump_rows($listAfterResp['body'], $listAfterResp['effective_url']);
        }

        $postRow = null;
        foreach ((array)$result['after'] as $row) {
            if (trim((string)($row['listing_id'] ?? '')) === $result['listingId']) {
                $postRow = $row;
                break;
            }
        }

        $result['renewOk'] = destacamos_response_has_success_signal($renewResp['body'])
            || ($renewResp['effective_url'] !== $result['renewUrl'])
            || ($postRow && empty($postRow['free_bump_available']));
        if (!$result['renewOk']) {
            $result['error'] = 'La web no confirmó la renovación gratis del anuncio.';
            $result['error_code'] = 'renew_not_confirmed';
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
            return $result;
        }

        $result['ok'] = true;
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['error'] = $e->getMessage();
        if (trim((string)$result['error_code']) === '') {
            $result['error_code'] = 'runtime_error';
        }
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } finally {
        if (!$externalSession) {
            destacamos_http_cleanup_session($session);
        }
    }
}

function destacamos_subir_gratis(array $payload): array
{
    $username = trim((string)($payload['username'] ?? ''));
    $password = trim((string)($payload['password'] ?? ''));
    $telefono = trim((string)($payload['telefono'] ?? ''));
    $timeoutMs = max(30000, (int)($payload['timeoutMs'] ?? 45000));

    $result = array(
        'ok' => false,
        'loginOk' => false,
        'listingFound' => false,
        'listingId' => null,
        'renewUrl' => null,
        'renewOk' => false,
        'currentUrl' => null,
        'error' => null,
    );

    if ($username === '' || $password === '' || $telefono === '') {
        $result['error'] = 'Faltan username, password o telefono';
        return $result;
    }

    if (!empty($payload['listingId']) || !empty($payload['allowed_listing_ids'])) {
        return destacamos_subir_gratis_disponible($payload);
    }

    $externalSession = is_array($payload['session'] ?? null) && !empty($payload['session']['cookie_file']);
    $session = $externalSession ? $payload['session'] : destacamos_http_session((int)ceil($timeoutMs / 1000));
    $session['timeout'] = max(15, (int)ceil($timeoutMs / 1000));
    $session['human'] = destacamos_payload_human_settings($payload);
    $debugLog = array(
        'enabled' => array_key_exists('debug_log', $payload) ? !empty($payload['debug_log']) : true,
        'steps' => array(),
        'context' => array(
            'mode' => 'free_bump_phone',
            'telefono' => $telefono,
            'external_session' => $externalSession,
        ),
    );

    try {
        $loginMeta = array();
        $usedExistingSession = false;
        if ($externalSession) {
            list($sessionOk, $sessionMeta) = destacamos_session_is_authenticated($session, $username);
            if ($sessionOk) {
                $usedExistingSession = true;
                $loginMeta = array(
                    'login_url' => trim((string)($sessionMeta['current_url'] ?? '')),
                    'session_reused' => true,
                );
            }
        }
        if (!$usedExistingSession) {
            list($okLogin, $loginMeta) = destacamos_login_session($session, $username, $password, $debugLog);
            if (!$okLogin) {
                $result['error'] = trim((string)($loginMeta['error'] ?? 'Login no confirmado'));
                $result['error_code'] = 'login_failed';
                $result['currentUrl'] = trim((string)($loginMeta['current_url'] ?? ''));
                $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
                if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
                return $result;
            }
        }
        $result['loginOk'] = true;
        $result['currentUrl'] = trim((string)($loginMeta['login_url'] ?? ''));
        $opts = destacamos_human_options($session);
        destacamos_human_pause($session, 'post_login_navigation_pause', (int)$opts['default_min_ms'], (int)$opts['default_max_ms']);

        $listUrl = 'https://www.destacamos.net/browse_listings.php';
        $listResp = destacamos_http_request($session, 'GET', $listUrl, array('timeout' => max(45, (int)ceil($timeoutMs / 1000))));
        destacamos_debug_record_response($debugLog, 'browse_listings_before', $listResp);
        if (!$listResp['ok']) {
            throw new RuntimeException('No se pudo abrir el listado de anuncios.');
        }
        $result['currentUrl'] = $listResp['effective_url'];
        destacamos_human_pause($session, 'browse_listings_read', (int)$opts['reading_min_ms'], (int)$opts['reading_max_ms']);

        $candidate = destacamos_find_free_bump_candidate($listResp['body'], $listResp['effective_url'], $telefono);
        if (!$candidate) {
            $result['error'] = 'No se encontró ningún anuncio con ese teléfono y "Subir gratis" activo';
            $result['error_code'] = 'no_free_listing_available';
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
            return $result;
        }

        $result['listingFound'] = true;
        $result['listingId'] = trim((string)($candidate['listing_id'] ?? ''));
        $result['renewUrl'] = trim((string)($candidate['renew_url'] ?? ''));

        destacamos_human_pause($session, 'renew_click_think', (int)$opts['click_min_ms'], (int)$opts['click_max_ms']);
        $renewResp = destacamos_http_request($session, 'GET', $result['renewUrl'], array(
            'referer' => $listResp['effective_url'],
            'timeout' => max(45, (int)ceil($timeoutMs / 1000)),
        ));
        destacamos_debug_record_response($debugLog, 'renew_listing', $renewResp);
        if (!$renewResp['ok']) {
            throw new RuntimeException('No se pudo ejecutar la acción de "Subir gratis".');
        }

        $result['currentUrl'] = $renewResp['effective_url'];
        $result['renewOk'] = destacamos_response_has_success_signal($renewResp['body']) || ($renewResp['effective_url'] !== $result['renewUrl']);
        if (!$result['renewOk']) {
            $result['error'] = 'La web no confirmó la renovación gratis del anuncio.';
            $result['error_code'] = 'renew_not_confirmed';
            $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
            if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
            return $result;
        }

        $result['ok'] = true;
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } catch (Throwable $e) {
        $result['ok'] = false;
        $result['error'] = $e->getMessage();
        if (trim((string)($result['error_code'] ?? '')) === '') {
            $result['error_code'] = 'runtime_error';
        }
        $result['humanTrace'] = array_values((array)($session['human_trace'] ?? array()));
        if (!empty($debugLog['enabled'])) $result['debug_log'] = $debugLog;
        return $result;
    } finally {
        if (!$externalSession) {
            destacamos_http_cleanup_session($session);
        }
    }
}
