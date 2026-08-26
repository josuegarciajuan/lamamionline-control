<?php

function is_logged_in() {
    return !empty($_SESSION['logged_in']);
}

function auth_is_admin() {
    return is_logged_in() && hash_equals('josue', (string)($_SESSION['username'] ?? ''));
}

function auth_josue_adicionales_unlocked() {
    return is_logged_in() && !empty($_SESSION['josue_adicionales_unlocked']);
}

function auth_can_manage_telefonos() {
    return auth_is_admin()
        || (($_SESSION['username'] ?? '') === 'telefono')
        || auth_josue_adicionales_unlocked();
}

function login_user($username, $password) {
    // Credenciales hardcodeadas
    if ($username === 'josue' && $password === 'vsomnos1Q#') {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = 'josue';
        $_SESSION['display_name'] = 'Josué';
        return true;
    }
    return false;
}

function logout_user() {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function auth_whitelist_ips() {
    $settings = settings_get();
    $raw = $settings['whitelist_ips'] ?? array();

    if (is_string($raw)) {
        $raw = preg_split('/[
,;]+/', $raw);
    }

    $ips = array();
    foreach ((array)$raw as $ip) {
        $ip = trim((string)$ip);
        if ($ip === '') continue;
        $ips[$ip] = $ip;
    }

    if (empty($ips)) {
        $ips['84.125.78.95'] = '84.125.78.95';
        $ips['79.116.229.72'] = '79.116.229.72';
    }

    return array_values($ips);
}

function auth_client_ip() {
    $headers = array(
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR',
    );

    foreach ($headers as $key) {
        if (empty($_SERVER[$key])) continue;

        $raw = (string)$_SERVER[$key];
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $raw);
            $raw = trim((string)($parts[0] ?? ''));
        }

        $raw = trim($raw);
        if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_IP)) {
            return $raw;
        }
    }

    return '';
}

function auth_is_whitelisted_ip($ip = null) {
    $ip = $ip === null ? auth_client_ip() : trim((string)$ip);
    if ($ip === '') return false;
    return in_array($ip, auth_whitelist_ips(), true);
}

function auth_auto_login_from_whitelist() {
    if (is_logged_in()) return false;
    if (request_get('page') === 'logout') return false;
    if (request_get('logged_out') === '1') return false;
    if (!auth_is_whitelisted_ip()) return false;

    // Si ya existe un dispositivo de confianza, dejar que él decida el usuario
    $cookieName = auth_trusted_device_cookie_name();
    if (!empty($_COOKIE[$cookieName]) && auth_is_trusted_device_token($_COOKIE[$cookieName])) {
        return false;
    }

    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = 'telefono';
    $_SESSION['display_name'] = 'Teléfono';
    $_SESSION['auth_via_whitelist'] = true;

    // Registrar automáticamente como dispositivo de confianza (cookie de 1 año)
    auth_register_trusted_device('IP ' . auth_client_ip(), 'telefono');
    return true;
}

// ── Trusted device (cookie-based, survives IP changes) ──

function auth_trusted_devices() {
    $settings = settings_get();
    return $settings['trusted_devices'] ?? array();
}

function auth_is_trusted_device_token($token) {
    $token = trim((string)$token);
    if ($token === '') return false;
    $devices = auth_trusted_devices();
    return isset($devices[$token]);
}

function auth_trusted_device_cookie_name() {
    return 'lamami_td';
}

function auth_generate_device_token() {
    return bin2hex(random_bytes(32));
}

function auth_register_trusted_device($label = '', $username = 'telefono') {
    $token = auth_generate_device_token();
    $devices = auth_trusted_devices();

    $label = trim((string)$label);
    if ($label === '') {
        $label = 'Dispositivo ' . date('Y-m-d H:i');
    }

    $devices[$token] = array(
        'label'        => $label,
        'username'     => $username,
        'created_at'   => now_datetime(),
        'last_used_at' => now_datetime(),
    );

    $settings = settings_get();
    $settings['trusted_devices'] = $devices;
    storage_write('settings.json', $settings);

    setcookie(
        auth_trusted_device_cookie_name(),
        $token,
        time() + 365 * 86400,
        '/',
        '',
        false,
        true
    );

    return $token;
}

function auth_remove_trusted_device($token) {
    $token = trim((string)$token);
    $devices = auth_trusted_devices();
    if (!isset($devices[$token])) return false;

    unset($devices[$token]);
    $settings = settings_get();
    $settings['trusted_devices'] = $devices;
    storage_write('settings.json', $settings);
    return true;
}

function auth_auto_login_from_trusted_device() {
    if (is_logged_in()) return false;
    if (request_get('page') === 'logout') return false;
    if (request_get('logged_out') === '1') return false;

    $cookieName = auth_trusted_device_cookie_name();
    if (empty($_COOKIE[$cookieName])) return false;

    $token = trim((string)$_COOKIE[$cookieName]);
    if (!auth_is_trusted_device_token($token)) return false;

    // Actualizar last_used_at y leer el username almacenado
    $devices = auth_trusted_devices();
    $device = $devices[$token];
    $device['last_used_at'] = now_datetime();
    $devices[$token] = $device;
    $settings = settings_get();
    $settings['trusted_devices'] = $devices;
    storage_write('settings.json', $settings);

    $username = $device['username'] ?? 'telefono';
    $displayName = ($username === 'josue') ? 'Josué' : (($username === 'telefono') ? 'Teléfono' : (($username === 'coche') ? 'Coche' : ucfirst($username)));

    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['display_name'] = $displayName;
    $_SESSION['auth_via_device'] = true;
    return true;
}
