<?php

function is_logged_in() {
    return !empty($_SESSION['logged_in']);
}

function login_user($username, $password) {
    // Credenciales hardcodeadas
    if ($username === 'josue' && $password === 'prueba1234') {
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

    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = 'whitelist';
    $_SESSION['display_name'] = 'Josué';
    $_SESSION['auth_via_whitelist'] = true;
    return true;
}
