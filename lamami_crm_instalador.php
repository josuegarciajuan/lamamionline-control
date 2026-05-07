<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = __DIR__;
$projectName = 'lamami_crm';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function write_file($path, $content) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, $content);
}

$files = array();

$files[$projectName . '/index.php'] = <<<'PHP'
<?php
require_once __DIR__ . '/app/bootstrap.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if ($page === 'logout') {
    logout_user();
    header('Location: index.php?page=login');
    exit;
}

if (!is_logged_in() && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post_actions();
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>LaMami CRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($page === 'login'): ?>
    <?php render_login_page(); ?>
<?php else: ?>
    <?php render_global_ui(); ?>
    <div class="layout">
        <?php render_sidebar($page); ?>
        <main class="main">
            <?php render_flash(); ?>
            <?php
            switch ($page) {
                case 'interesadas':
                    render_interesadas_page();
                    break;
                case 'clientas':
                    render_clientas_page();
                    break;
                case 'bots':
                    render_bots_page();
                    break;
                case 'informes':
                    render_informes_page();
                    break;
                default:
                    render_dashboard_page();
                    break;
            }
            ?>
        </main>
    </div>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
PHP;

$files[$projectName . '/app/bootstrap.php'] = <<<'PHP'
<?php
session_start();
date_default_timezone_set('Europe/Madrid');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', BASE_PATH . '/data');

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/actions.php';
require_once APP_PATH . '/views.php';

bootstrap_storage();
PHP;

$files[$projectName . '/app/helpers.php'] = <<<'PHP'
<?php

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function now_datetime() {
    return date('Y-m-d H:i:s');
}

function today_date() {
    return date('Y-m-d');
}

function today_datetime_local() {
    return date('Y-m-d\TH:i');
}

function generate_id($prefix) {
    try {
        return $prefix . '_' . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        return $prefix . '_' . substr(md5(uniqid('', true)), 0, 8);
    }
}

function to_float($value, $default = 0) {
    if ($value === null || $value === '') return (float)$default;
    $value = str_replace(',', '.', (string)$value);
    return (float)$value;
}

function euro($number) {
    return number_format((float)$number, 2, ',', '.') . ' €';
}

function set_flash($type, $message, $fx = '') {
    $_SESSION['flash'] = array('type' => $type, 'message' => $message, 'fx' => $fx);
}

function get_flash() {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function request_get($key, $default = '') {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function request_post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

function days_between_dates($from, $to) {
    $a = strtotime((string)$from);
    $b = strtotime((string)$to);
    if (!$a || !$b || $b < $a) return null;
    return round(($b - $a) / 86400, 2);
}

function sort_desc_by_key($items, $key) {
    usort($items, function ($a, $b) use ($key) {
        $av = isset($a[$key]) ? $a[$key] : '';
        $bv = isset($b[$key]) ? $b[$key] : '';
        return strcmp((string)$bv, (string)$av);
    });
    return $items;
}

function clienta_estado_label($estado) {
    if ($estado === 'alta') return 'Alta';
    if ($estado === 'baja') return 'Baja';
    return $estado;
}

function interesada_estado_label($estado) {
    if ($estado === 'nueva') return 'Nueva';
    if ($estado === 'atendida') return 'Atendida';
    if ($estado === 'convertida') return 'Convertida';
    if ($estado === 'descartada') return 'Descartada';
    return $estado;
}

function filter_rows_between_dates($rows, $field, $from, $to) {
    $fromTs = $from !== '' ? strtotime($from . ' 00:00:00') : null;
    $toTs = $to !== '' ? strtotime($to . ' 23:59:59') : null;
    $out = array();
    foreach ($rows as $row) {
        $raw = isset($row[$field]) ? $row[$field] : '';
        $ts = strtotime(str_replace('T', ' ', $raw));
        if (!$ts) continue;
        if ($fromTs !== null && $ts < $fromTs) continue;
        if ($toTs !== null && $ts > $toTs) continue;
        $out[] = $row;
    }
    return $out;
}

function lead_totals($rows) {
    $count = count($rows);
    $money = 0;
    foreach ($rows as $row) {
        $money += isset($row['precio_lead']) ? (float)$row['precio_lead'] : 0;
    }
    return array('count' => $count, 'money' => $money);
}

function lead_success_message($price) {
    return '¡Lead confirmado! +' . euro($price) . ' al marcador.';
}

function interesada_state_feedback($estado) {
    if ($estado === 'atendida') {
        return array('ok', '¡Bien! Ya está atendida. Vamos a convertirla.', 'motivate');
    }
    if ($estado === 'convertida') {
        return array('ok', '¡Excelente! Interesada convertida en clienta.', 'celebrate');
    }
    if ($estado === 'descartada') {
        return array('ok', 'Interesada marcada como descartada.', '');
    }
    return array('ok', 'Estado actualizado.', '');
}
PHP;

$files[$projectName . '/app/storage.php'] = <<<'PHP'
<?php

function bootstrap_storage() {
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0775, true);
    }

    $defaultUsers = array(
        array(
            'id' => 'usr_admin',
            'username' => 'nuria',
            'password' => 'josue',
            'name' => 'Nuria'
        )
    );

    $defaults = array(
        'users.json' => $defaultUsers,
        'clientes.json' => array(),
        'bots.json' => array(),
        'leads.json' => array(),
        'interesadas.json' => array(),
        'settings.json' => array(
            'lead_default_price' => 10,
            'brand' => 'LaMami CRM'
        )
    );

    foreach ($defaults as $file => $content) {
        $path = DATA_PATH . '/' . $file;
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

function storage_read($file) {
    $path = DATA_PATH . '/' . $file;
    if (!file_exists($path)) return array();
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

function storage_write($file, $data) {
    $path = DATA_PATH . '/' . $file;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function storage_find_by_id($file, $id) {
    $rows = storage_read($file);
    foreach ($rows as $row) {
        if (isset($row['id']) && $row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function storage_upsert($file, $row) {
    $rows = storage_read($file);
    $updated = false;
    foreach ($rows as $i => $item) {
        if (isset($item['id']) && $item['id'] === $row['id']) {
            $rows[$i] = array_merge($item, $row);
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $rows[] = $row;
    }
    storage_write($file, array_values($rows));
}

function storage_delete($file, $id) {
    $rows = storage_read($file);
    $out = array();
    foreach ($rows as $row) {
        if (!isset($row['id']) || $row['id'] !== $id) {
            $out[] = $row;
        }
    }
    storage_write($file, array_values($out));
}

function clientes_index() {
    $items = storage_read('clientes.json');
    $idx = array();
    foreach ($items as $item) {
        $idx[$item['id']] = $item;
    }
    return $idx;
}

function bots_index() {
    $items = storage_read('bots.json');
    $idx = array();
    foreach ($items as $item) {
        $idx[$item['id']] = $item;
    }
    return $idx;
}

function settings_get() {
    return storage_read('settings.json');
}

function get_active_clientas() {
    $items = storage_read('clientes.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['estado']) && $item['estado'] === 'alta') {
            $out[] = $item;
        }
    }
    return $out;
}

function get_clienta_current_bot($clientaId) {
    $bots = storage_read('bots.json');
    foreach ($bots as $bot) {
        if (isset($bot['cliente_id']) && $bot['cliente_id'] === $clientaId) {
            return $bot;
        }
    }
    return null;
}

function get_leads_for_clienta($clientaId) {
    $items = storage_read('leads.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['cliente_id']) && $item['cliente_id'] === $clientaId) {
            $out[] = $item;
        }
    }
    return $out;
}

function get_leads_for_bot($botId) {
    $items = storage_read('leads.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['bot_id']) && $item['bot_id'] === $botId) {
            $out[] = $item;
        }
    }
    return $out;
}
PHP;

$files[$projectName . '/app/auth.php'] = <<<'PHP'
<?php

function is_logged_in() {
    return !empty($_SESSION['logged_in']);
}

function login_user($username, $password) {
    $users = storage_read('users.json');
    foreach ($users as $user) {
        if (
            isset($user['username'], $user['password']) &&
            $user['username'] === $username &&
            $user['password'] === $password
        ) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['display_name'] = isset($user['name']) ? $user['name'] : $user['username'];
            return true;
        }
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
PHP;

$files[$projectName . '/app/actions.php'] = <<<'PHP'
<?php

function handle_post_actions() {
    $action = request_post('action');

    if ($action === 'login') {
        $user = trim(request_post('username'));
        $pass = (string)request_post('password');
        if (login_user($user, $pass)) {
            set_flash('ok', 'Bienvenida al sistema.');
            redirect_to('index.php?page=dashboard');
        }
        set_flash('error', 'Usuario o contraseña incorrectos.');
        redirect_to('index.php?page=login');
    }

    if (!is_logged_in()) {
        redirect_to('index.php?page=login');
    }

    switch ($action) {
        case 'save_interesada':
            action_save_interesada();
            break;
        case 'delete_interesada':
            action_delete_generic('interesadas.json', 'Interesada eliminada.', 'index.php?page=interesadas');
            break;
        case 'set_interesada_estado':
            action_set_interesada_estado();
            break;
        case 'convert_interesada':
            action_convert_interesada();
            break;
        case 'save_clienta':
            action_save_clienta();
            break;
        case 'baja_clienta':
            action_baja_clienta();
            break;
        case 'alta_clienta':
            action_alta_clienta();
            break;
        case 'save_bot':
            action_save_bot();
            break;
        case 'delete_bot':
            action_delete_generic('bots.json', 'Bot eliminado.', 'index.php?page=bots');
            break;
        case 'quick_lead':
            action_quick_lead();
            break;
        case 'delete_lead':
            action_delete_lead();
            break;
    }
}

function action_delete_generic($file, $message, $redirect) {
    $id = request_post('id');
    if ($id !== '') {
        storage_delete($file, $id);
    }
    set_flash('ok', $message);
    redirect_to($redirect);
}

function action_save_interesada() {
    $id = request_post('id');
    if ($id === '') $id = generate_id('int');

    $existing = storage_find_by_id('interesadas.json', $id);

    $row = array(
        'id' => $id,
        'telefono' => trim(request_post('telefono')),
        'observaciones' => trim(request_post('observaciones')),
        'movil_origen' => trim(request_post('movil_origen')),
        'estado' => trim(request_post('estado', 'nueva')),
        'cliente_id' => trim(request_post('cliente_id')),
        'updated_at' => now_datetime()
    );

    if ($existing && isset($existing['created_at'])) {
        $row['created_at'] = $existing['created_at'];
    } else {
        $row['created_at'] = now_datetime();
    }

    if ($existing && isset($existing['convertida_at'])) {
        $row['convertida_at'] = $existing['convertida_at'];
    }

    storage_upsert('interesadas.json', $row);
    set_flash('ok', 'Interesada guardada correctamente.');
    redirect_to('index.php?page=interesadas');
}

function action_set_interesada_estado() {
    $id = request_post('id');
    $estado = trim(request_post('estado'));
    $row = storage_find_by_id('interesadas.json', $id);

    if (!$row) {
        set_flash('error', 'Interesada no encontrada.');
        redirect_to('index.php?page=interesadas');
    }

    $row['estado'] = $estado;
    $row['updated_at'] = now_datetime();
    storage_upsert('interesadas.json', $row);

    $fb = interesada_state_feedback($estado);
    set_flash($fb[0], $fb[1], $fb[2]);
    redirect_to('index.php?page=interesadas');
}

function action_convert_interesada() {
    $interesadaId = request_post('interesada_id');
    $interesada = storage_find_by_id('interesadas.json', $interesadaId);

    if (!$interesada) {
        set_flash('error', 'No se encontró la interesada.');
        redirect_to('index.php?page=interesadas');
    }

    $clienteId = generate_id('cli');
    $cliente = array(
        'id' => $clienteId,
        'nombre' => trim(request_post('nombre')),
        'telefono' => trim(request_post('telefono')),
        'localidad' => trim(request_post('localidad')),
        'provincia' => trim(request_post('provincia')),
        'fecha_alta' => request_post('fecha_alta', today_date()),
        'precio_alta' => to_float(request_post('precio_alta'), 0),
        'modo_pago' => trim(request_post('modo_pago')),
        'notas' => trim(request_post('notas')),
        'estado' => 'alta',
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'source_interesada_id' => $interesadaId
    );

    storage_upsert('clientes.json', $cliente);

    $interesadas = storage_read('interesadas.json');
    foreach ($interesadas as $i => $item) {
        if (isset($item['id']) && $item['id'] === $interesadaId) {
            $interesadas[$i]['estado'] = 'convertida';
            $interesadas[$i]['cliente_id'] = $clienteId;
            $interesadas[$i]['convertida_at'] = now_datetime();
            $interesadas[$i]['updated_at'] = now_datetime();
            break;
        }
    }
    storage_write('interesadas.json', $interesadas);

    set_flash('ok', '¡Excelente! Interesada convertida en clienta.', 'celebrate');
    redirect_to('index.php?page=clientas');
}

function action_save_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);

    if (!$existing) {
        set_flash('error', 'No se puede crear una clienta directamente. Debe venir de una interesada.');
        redirect_to('index.php?page=interesadas');
    }

    $row = array(
        'id' => $id,
        'nombre' => trim(request_post('nombre')),
        'telefono' => trim(request_post('telefono')),
        'localidad' => trim(request_post('localidad')),
        'provincia' => trim(request_post('provincia')),
        'fecha_alta' => request_post('fecha_alta', $existing['fecha_alta']),
        'precio_alta' => to_float(request_post('precio_alta'), $existing['precio_alta']),
        'modo_pago' => trim(request_post('modo_pago')),
        'notas' => trim(request_post('notas')),
        'estado' => trim(request_post('estado', $existing['estado'])),
        'updated_at' => now_datetime()
    );

    $row['created_at'] = isset($existing['created_at']) ? $existing['created_at'] : now_datetime();
    $row['source_interesada_id'] = isset($existing['source_interesada_id']) ? $existing['source_interesada_id'] : '';
    if (isset($existing['fecha_baja'])) $row['fecha_baja'] = $existing['fecha_baja'];

    storage_upsert('clientes.json', $row);
    set_flash('ok', 'Clienta actualizada.');
    redirect_to('index.php?page=clientas&edit=' . urlencode($id));
}

function action_baja_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);
    if (!$existing) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to('index.php?page=clientas');
    }
    $existing['estado'] = 'baja';
    $existing['fecha_baja'] = request_post('fecha_baja', today_date());
    $existing['updated_at'] = now_datetime();
    storage_upsert('clientes.json', $existing);
    set_flash('ok', 'Clienta dada de baja.');
    redirect_to('index.php?page=clientas&edit=' . urlencode($id));
}

function action_alta_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);
    if (!$existing) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to('index.php?page=clientas');
    }
    $existing['estado'] = 'alta';
    $existing['updated_at'] = now_datetime();
    storage_upsert('clientes.json', $existing);
    set_flash('ok', 'Clienta reactivada.');
    redirect_to('index.php?page=clientas&edit=' . urlencode($id));
}

function action_save_bot() {
    $id = request_post('id');
    if ($id === '') $id = generate_id('bot');

    $row = array(
        'id' => $id,
        'nombre_bot' => trim(request_post('nombre_bot')),
        'telefono_bot' => trim(request_post('telefono_bot')),
        'waha_port' => trim(request_post('waha_port')),
        'cliente_id' => trim(request_post('cliente_id')),
        'ubicacion_maps' => trim(request_post('ubicacion_maps')),
        'zona' => trim(request_post('zona')),
        'servicios' => trim(request_post('servicios')),
        'tarifas' => trim(request_post('tarifas')),
        'estado' => trim(request_post('estado')),
        'updated_at' => now_datetime()
    );

    $existing = storage_find_by_id('bots.json', $id);
    if ($existing && isset($existing['created_at'])) {
        $row['created_at'] = $existing['created_at'];
    } else {
        $row['created_at'] = now_datetime();
    }

    storage_upsert('bots.json', $row);
    set_flash('ok', 'Bot guardado correctamente.');
    redirect_to('index.php?page=bots&edit=' . urlencode($id));
}

function build_lead_row($id, $clientaId, $fechaHora, $precioLead, $observaciones) {
    $clientas = clientes_index();
    if (!isset($clientas[$clientaId])) {
        return array(false, 'Clienta no encontrada.');
    }

    $clienta = $clientas[$clientaId];
    $bot = get_clienta_current_bot($clientaId);

    if (!$bot) {
        return array(false, 'Esta clienta no tiene bot vinculado. No se puede registrar el lead.');
    }

    return array(true, array(
        'id' => $id,
        'cliente_id' => $clientaId,
        'cliente_nombre' => isset($clienta['nombre']) ? $clienta['nombre'] : '',
        'bot_id' => isset($bot['id']) ? $bot['id'] : '',
        'bot_nombre' => isset($bot['nombre_bot']) ? $bot['nombre_bot'] : '',
        'fecha_hora' => $fechaHora,
        'precio_lead' => $precioLead,
        'observaciones' => $observaciones,
        'updated_at' => now_datetime()
    ));
}

function action_quick_lead() {
    $settings = settings_get();
    $defaultPrice = isset($settings['lead_default_price']) ? $settings['lead_default_price'] : 10;
    $id = generate_id('lead');
    $clientaId = trim(request_post('cliente_id'));

    list($ok, $result) = build_lead_row(
        $id,
        $clientaId,
        request_post('fecha_hora', today_datetime_local()),
        request_post('precio_lead') === '' ? (float)$defaultPrice : to_float(request_post('precio_lead')),
        trim(request_post('observaciones'))
    );

    if (!$ok) {
        set_flash('error', $result);
        redirect_to('index.php?page=clientas&edit=' . urlencode($clientaId));
    }

    $row = $result;
    $row['created_at'] = now_datetime();

    storage_upsert('leads.json', $row);
    set_flash('ok', lead_success_message($row['precio_lead']), 'money');
    redirect_to('index.php?page=clientas&edit=' . urlencode($clientaId));
}

function action_delete_lead() {
    $id = request_post('id');
    $clientaId = request_post('clienta_id');
    if ($id !== '') {
        storage_delete('leads.json', $id);
    }
    set_flash('ok', 'Lead eliminado.');
    redirect_to('index.php?page=clientas&edit=' . urlencode($clientaId));
}
PHP;

$files[$projectName . '/app/views.php'] = <<<'PHP'
<?php

function render_global_ui() {
    echo '<div id="floatingToast" class="floating-toast"></div>';
    echo '<div id="moneyRain" class="money-rain"></div>';
}

function render_flash() {
    $flash = get_flash();
    if (!$flash) return;
    $fx = isset($flash['fx']) ? $flash['fx'] : '';
    echo '<div class="flash flash-' . e($flash['type']) . '" data-fx="' . e($fx) . '">' . e($flash['message']) . '</div>';
}

function render_login_page() {
    echo '<div class="login-wrap">';
    echo '  <div class="login-card">';
    echo '      <div class="brand">LaMami <span>CRM</span></div>';
    echo '      <div class="subtitle center">Acceso al sistema</div>';
    render_flash();
    echo '      <form method="post">';
    echo '          <input type="hidden" name="action" value="login">';
    echo '          <div class="field"><label>Usuario</label><input type="text" name="username" required></div>';
    echo '          <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>';
    echo '          <button type="submit" class="btn-primary">Entrar</button>';
    echo '      </form>';
    echo '      <div class="login-help">Login: nuria / josue</div>';
    echo '  </div>';
    echo '</div>';
}

function render_sidebar($page) {
    $name = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Usuario';

    $menu = array(
        'dashboard' => 'Dashboard',
        'interesadas' => 'Interesadas',
        'clientas' => 'Clientas',
        'bots' => 'Bots',
        'informes' => 'Informes',
        'logout' => 'Salir'
    );

    echo '<aside class="sidebar">';
    echo '<div class="brand">LaMami <span>CRM</span></div>';
    echo '<div class="userbox">Hola, ' . e($name) . '</div>';
    echo '<nav class="nav">';
    foreach ($menu as $slug => $label) {
        $class = ($page === $slug) ? 'active' : '';
        echo '<a class="' . $class . '" href="index.php?page=' . e($slug) . '">' . e($label) . '</a>';
    }
    echo '</nav>';
    echo '</aside>';
}

function page_header($title, $subtitle = '') {
    echo '<div class="page-head">';
    echo '<div>';
    echo '<h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . e($subtitle) . '</p>';
    }
    echo '</div>';
    echo '</div>';
}

function dashboard_card($title, $value, $money = false) {
    echo '<section class="panel stat">';
    echo '<div class="stat-label">' . e($title) . '</div>';
    echo '<div class="stat-value ' . ($money ? 'money' : '') . '">' . e($value) . '</div>';
    echo '</section>';
}

function render_dashboard_page() {
    $clientes = storage_read('clientes.json');
    $bots = storage_read('bots.json');
    $leads = storage_read('leads.json');
    $interesadas = storage_read('interesadas.json');

    $clientasAlta = 0;
    $clientasBaja = 0;
    $ingresosAltas = 0;
    foreach ($clientes as $c) {
        if (isset($c['estado']) && $c['estado'] === 'alta') $clientasAlta++;
        if (isset($c['estado']) && $c['estado'] === 'baja') $clientasBaja++;
        $ingresosAltas += isset($c['precio_alta']) ? (float)$c['precio_alta'] : 0;
    }

    $ingresosLeads = 0;
    foreach ($leads as $l) $ingresosLeads += isset($l['precio_lead']) ? (float)$l['precio_lead'] : 0;

    $nuevas = 0;
    $atendidas = 0;
    $convertidas = 0;
    foreach ($interesadas as $i) {
        if (!isset($i['estado'])) continue;
        if ($i['estado'] === 'nueva') $nuevas++;
        if ($i['estado'] === 'atendida') $atendidas++;
        if ($i['estado'] === 'convertida') $convertidas++;
    }
    $conversion = count($interesadas) > 0 ? round(($convertidas / count($interesadas)) * 100, 1) : 0;

    page_header('Dashboard', 'Resumen general de actividad e ingresos');

    echo '<div class="cards four">';
    dashboard_card('Clientas activas', $clientasAlta);
    dashboard_card('Clientas de baja', $clientasBaja);
    dashboard_card('Leads', count($leads));
    dashboard_card('Conversión interesadas', $conversion . '%');
    echo '</div>';

    echo '<div class="cards four">';
    dashboard_card('Interesadas nuevas', $nuevas);
    dashboard_card('Interesadas atendidas', $atendidas);
    dashboard_card('Interesadas convertidas', $convertidas);
    dashboard_card('Bots', count($bots));
    echo '</div>';

    echo '<div class="cards three">';
    dashboard_card('Ingresos altas', euro($ingresosAltas), true);
    dashboard_card('Ingresos leads', euro($ingresosLeads), true);
    dashboard_card('Ingresos totales', euro($ingresosAltas + $ingresosLeads), true);
    echo '</div>';

    $months = array();
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime('-' . $i . ' month'));
        $months[$key] = array('count' => 0, 'income' => 0);
    }
    foreach ($leads as $lead) {
        $ts = strtotime(str_replace('T', ' ', isset($lead['fecha_hora']) ? $lead['fecha_hora'] : ''));
        if ($ts) {
            $key = date('Y-m', $ts);
            if (!isset($months[$key])) $months[$key] = array('count' => 0, 'income' => 0);
            $months[$key]['count']++;
            $months[$key]['income'] += isset($lead['precio_lead']) ? (float)$lead['precio_lead'] : 0;
        }
    }

    $clientLeadCounts = array();
    foreach ($leads as $lead) {
        $label = isset($lead['cliente_nombre']) && $lead['cliente_nombre'] !== '' ? $lead['cliente_nombre'] : 'Sin clienta';
        if (!isset($clientLeadCounts[$label])) $clientLeadCounts[$label] = 0;
        $clientLeadCounts[$label]++;
    }
    if (empty($clientLeadCounts)) {
        $clientLeadCounts['Sin datos'] = 1;
    }

    echo '<div class="cards two">';
    echo '<section class="panel"><h2>Leads e ingresos por mes</h2><canvas id="chartMonthly"></canvas></section>';
    echo '<section class="panel"><h2>Leads por clienta</h2><canvas id="chartClients"></canvas></section>';
    echo '</div>';

    echo '<script>';
    echo 'new Chart(document.getElementById("chartMonthly"), {';
    echo 'type:"bar",';
    echo 'data:{labels:' . json_encode(array_keys($months)) . ',datasets:[';
    echo '{label:"Leads",data:' . json_encode(array_map(function($m){ return $m["count"]; }, $months)) . '},';
    echo '{label:"Ingresos leads",type:"line",data:' . json_encode(array_map(function($m){ return $m["income"]; }, $months)) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false}});';
    echo 'new Chart(document.getElementById("chartClients"), {';
    echo 'type:"doughnut",';
    echo 'data:{labels:' . json_encode(array_keys($clientLeadCounts)) . ',datasets:[{data:' . json_encode(array_values($clientLeadCounts)) . '}]},';
    echo 'options:{responsive:true,maintainAspectRatio:false}});';
    echo '</script>';
}

function render_interesadas_page() {
    $items = storage_read('interesadas.json');
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('interesadas.json', $editId);
    }

    $convert = null;
    $convertId = request_get('convert');
    if ($convertId !== '') {
        $convert = storage_find_by_id('interesadas.json', $convertId);
    }

    page_header('Interesadas', 'Trabajo comercial visual y rápido');

    if ($convert) {
        echo '<section class="panel panel-space panel-highlight-success">';
        echo '<h2>Convertir interesada a clienta</h2>';
        echo '<p class="muted big-msg">Vamos a rematar esta oportunidad. Completa el alta y conviértela en clienta.</p>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="convert_interesada">';
        echo '<input type="hidden" name="interesada_id" value="' . e($convert['id']) . '">';
        field_input('nombre', 'Nombre', '', true);
        field_input('telefono', 'Teléfono', isset($convert['telefono']) ? $convert['telefono'] : '');
        field_input('localidad', 'Localidad', '');
        field_input('provincia', 'Provincia', '');
        field_input('fecha_alta', 'Fecha alta', today_date(), false, 'date');
        field_input('precio_alta', 'Precio alta', '');
        field_input('modo_pago', 'Modo pago', '');
        field_textarea('notas', 'Notas internas', '', 3);
        echo '<div class="full"><button class="btn-primary btn-big">Convertir en clienta</button></div>';
        echo '</form>';
        echo '</section>';
    }

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<h2>' . ($edit ? 'Editar interesada' : 'Nueva interesada') . '</h2>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_interesada">';
    echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';

    field_input('telefono', 'Teléfono', $edit ? $edit['telefono'] : '', true);
    field_input('movil_origen', 'De qué móvil viene', $edit ? $edit['movil_origen'] : '');

    echo '<div class="field">';
    echo '<label>Estado</label>';
    echo '<select name="estado">';
    $estadoActual = $edit ? $edit['estado'] : 'nueva';
    $opts = array('nueva' => 'Nueva', 'atendida' => 'Atendida', 'convertida' => 'Convertida', 'descartada' => 'Descartada');
    foreach ($opts as $k => $label) {
        $sel = ($estadoActual === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    field_input('cliente_id', 'ID clienta (solo si ya fue convertida)', $edit ? $edit['cliente_id'] : '');
    field_textarea('observaciones', 'Observaciones', $edit ? $edit['observaciones'] : '', 4);
    echo '<div class="full"><button class="btn-primary">Guardar interesada</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado comercial</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay interesadas todavía.</div>';
    } else {
        $cidx = clientes_index();
        $items = sort_desc_by_key($items, 'created_at');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Teléfono</th><th>Origen</th><th>Estado</th><th>Vínculo</th><th>Tiempo hasta alta</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($items as $row) {
            $dias = '-';
            $vinculo = '-';
            $estado = isset($row['estado']) ? $row['estado'] : '';
            $rowClass = 'row-state-' . $estado;

            if (
                $estado === 'convertida' &&
                isset($row['cliente_id']) && isset($cidx[$row['cliente_id']])
            ) {
                $clienta = $cidx[$row['cliente_id']];
                $vinculo = 'Clienta: ' . (isset($clienta['nombre']) ? $clienta['nombre'] : '');
                $diasCalc = days_between_dates(
                    isset($row['created_at']) ? $row['created_at'] : '',
                    isset($clienta['fecha_alta']) ? $clienta['fecha_alta'] : ''
                );
                if ($diasCalc !== null) $dias = $diasCalc . ' días';
            }

            echo '<tr class="' . e($rowClass) . '">';
            echo '<td><strong>' . e($row['telefono']) . '</strong><br><span class="muted">' . e($row['observaciones']) . '</span></td>';
            echo '<td>' . e($row['movil_origen']) . '</td>';
            echo '<td><span class="pill state-' . e($estado) . '">' . e(interesada_estado_label($estado)) . '</span></td>';
            echo '<td>' . e($vinculo) . '</td>';
            echo '<td>' . e($dias) . '</td>';
            echo '<td>';
            echo '<div class="action-stack">';
            echo '<a class="mini-link" href="index.php?page=interesadas&edit=' . e($row['id']) . '">Editar</a>';

            if ($estado === 'nueva') {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Marcar como atendida?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="atendida">';
                echo '<button class="btn-warning-mini">Pasar a atendida</button>';
                echo '</form>';
            } elseif ($estado === 'atendida') {
                echo '<a class="mini-link success-link" href="index.php?page=interesadas&convert=' . e($row['id']) . '">Convertir</a>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Marcar como descartada?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="descartada">';
                echo '<button class="btn-danger-mini">Descartar</button>';
                echo '</form>';
            } elseif ($estado === 'descartada') {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Reabrir como atendida?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="atendida">';
                echo '<button class="btn-ok-mini">Reactivar</button>';
                echo '</form>';
            }

            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar interesada?\')">';
            echo '<input type="hidden" name="action" value="delete_interesada">';
            echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
            echo '<button class="btn-danger-mini">Borrar</button>';
            echo '</form>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_clientas_page() {
    $items = storage_read('clientes.json');
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('clientes.json', $editId);
    }

    page_header('Clientas', 'Solo se crean desde interesadas. Aquí se editan y trabajan los leads.');

    echo '<div class="cards two">';
    echo '<section class="panel">';
    if ($edit) {
        echo '<h2>Ficha de clienta</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_clienta">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        field_input('nombre', 'Nombre', $edit['nombre'], true);
        field_input('telefono', 'Teléfono', $edit['telefono']);
        field_input('localidad', 'Localidad', $edit['localidad']);
        field_input('provincia', 'Provincia', $edit['provincia']);
        field_input('fecha_alta', 'Fecha alta', $edit['fecha_alta'], false, 'date');
        field_input('precio_alta', 'Precio alta', $edit['precio_alta']);
        field_input('modo_pago', 'Modo pago', $edit['modo_pago']);
        field_input('estado', 'Estado', $edit['estado']);
        field_textarea('notas', 'Notas', isset($edit['notas']) ? $edit['notas'] : '', 4);
        echo '<div class="full"><button class="btn-primary">Guardar cambios</button></div>';
        echo '</form>';

        echo '<div class="mini-actions-bar">';
        if (isset($edit['estado']) && $edit['estado'] === 'alta') {
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Dar de baja a la clienta?\')">';
            echo '<input type="hidden" name="action" value="baja_clienta">';
            echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
            echo '<input type="hidden" name="fecha_baja" value="' . e(today_date()) . '">';
            echo '<button class="btn-warning-mini">Dar de baja</button>';
            echo '</form>';
        } else {
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="alta_clienta">';
            echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
            echo '<button class="btn-ok-mini">Reactivar</button>';
            echo '</form>';
        }
        echo '</div>';

        $intTxt = '-';
        if (!empty($edit['source_interesada_id'])) {
            $int = storage_find_by_id('interesadas.json', $edit['source_interesada_id']);
            if ($int) {
                $intTxt = 'Interesada origen: ' . (isset($int['telefono']) ? $int['telefono'] : $edit['source_interesada_id']);
            }
        }
        echo '<div class="info-strip">' . e($intTxt) . '</div>';

        render_clienta_leads_panel($edit);
    } else {
        echo '<h2>Alta de clientas</h2>';
        echo '<div class="empty">Las clientas no se crean aquí. Deben venir desde interesadas mediante conversión.</div>';
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay clientas todavía.</div>';
    } else {
        $items = sort_desc_by_key($items, 'fecha_alta');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Nombre</th><th>Estado</th><th>Teléfono</th><th>Alta</th><th>Bot actual</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($items as $row) {
            $bot = get_clienta_current_bot($row['id']);
            $botTxt = $bot ? $bot['nombre_bot'] : 'Sin bot';
            echo '<tr>';
            echo '<td><strong>' . e($row['nombre']) . '</strong></td>';
            echo '<td><span class="pill state-' . e($row['estado']) . '">' . e(clienta_estado_label($row['estado'])) . '</span></td>';
            echo '<td>' . e($row['telefono']) . '</td>';
            echo '<td>' . e($row['fecha_alta']) . '<br><span class="muted">' . e(euro($row['precio_alta'])) . '</span></td>';
            echo '<td>' . e($botTxt) . '</td>';
            echo '<td><a class="mini-link" href="index.php?page=clientas&edit=' . e($row['id']) . '">Abrir ficha</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_clienta_leads_panel($clienta) {
    $bot = get_clienta_current_bot($clienta['id']);
    echo '<hr class="sep">';
    echo '<div class="lead-zone">';
    echo '<h2>Leads de esta clienta</h2>';

    if (!$bot) {
        echo '<div class="empty">Esta clienta no tiene bot vinculado. Vincula un bot para poder registrar leads.</div>';
        echo '</div>';
        return;
    }

    echo '<div class="money-callout">';
    echo '<div class="money-title">Registrar lead rápido</div>';
    echo '<form method="post" class="lead-quick-inline" onsubmit="return confirmLeadSubmit(this);">';
    echo '<input type="hidden" name="action" value="quick_lead">';
    echo '<input type="hidden" name="cliente_id" value="' . e($clienta['id']) . '">';
    echo '<input type="hidden" name="fecha_hora" value="' . e(today_datetime_local()) . '">';
    echo '<input type="text" name="precio_lead" value="10" class="money-input">';
    echo '<input type="text" name="observaciones" placeholder="Observación opcional" class="money-note">';
    echo '<button class="btn-money">+ Registrar lead</button>';
    echo '</form>';
    echo '<div class="muted">Bot actual vinculado: ' . e($bot['nombre_bot']) . '</div>';
    echo '</div>';

    $from = request_get('client_leads_from', '');
    $to = request_get('client_leads_to', '');
    $leads = get_leads_for_clienta($clienta['id']);
    $leads = filter_rows_between_dates($leads, 'fecha_hora', $from, $to);
    $leads = sort_desc_by_key($leads, 'fecha_hora');
    $totals = lead_totals($leads);

    echo '<form method="get" class="toolbar toolbar-small lead-filter-bar">';
    echo '<input type="hidden" name="page" value="clientas">';
    echo '<input type="hidden" name="edit" value="' . e($clienta['id']) . '">';
    echo '<div class="field"><label>Desde</label><input type="date" name="client_leads_from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="client_leads_to" value="' . e($to) . '"></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
    echo '</form>';

    if (empty($leads)) {
        echo '<div class="empty">No hay leads para esta clienta con esos filtros.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Precio</th><th>Clienta</th><th>Bot</th><th>Observación</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($leads as $lead) {
            echo '<tr>';
            echo '<td>' . e(str_replace('T', ' ', $lead['fecha_hora'])) . '</td>';
            echo '<td><span class="money-chip">' . e(euro($lead['precio_lead'])) . '</span></td>';
            echo '<td>' . e($lead['cliente_nombre']) . '</td>';
            echo '<td>' . e($lead['bot_nombre']) . '</td>';
            echo '<td>' . e(isset($lead['observaciones']) ? $lead['observaciones'] : '') . '</td>';
            echo '<td>';
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Seguro que quieres eliminar este lead?\')">';
            echo '<input type="hidden" name="action" value="delete_lead">';
            echo '<input type="hidden" name="id" value="' . e($lead['id']) . '">';
            echo '<input type="hidden" name="clienta_id" value="' . e($clienta['id']) . '">';
            echo '<button class="btn-danger-mini">Eliminar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<div class="totals-bar">';
    echo '<div><strong>Total leads:</strong> ' . e($totals['count']) . '</div>';
    echo '<div><strong>Total dinero:</strong> <span class="money-chip big">' . e(euro($totals['money'])) . '</span></div>';
    echo '</div>';
    echo '</div>';
}

function render_bots_page() {
    $items = storage_read('bots.json');
    $clientes = get_active_clientas();
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('bots.json', $editId);
    }

    page_header('Bots', 'Asignación actual y lectura de leads históricos');

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<h2>' . ($edit ? 'Ficha de bot' : 'Nuevo bot') . '</h2>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_bot">';
    echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';

    field_input('nombre_bot', 'Nombre bot', $edit ? $edit['nombre_bot'] : '', true);
    field_input('telefono_bot', 'Teléfono bot', $edit ? $edit['telefono_bot'] : '');
    field_input('waha_port', 'WAHA port', $edit ? $edit['waha_port'] : '');
    field_select_clienta('cliente_id', 'Clienta actual vinculada', $clientes, $edit ? $edit['cliente_id'] : '');
    field_input('ubicacion_maps', 'Ubicación Maps', $edit ? $edit['ubicacion_maps'] : '');
    field_input('zona', 'Zona', $edit ? $edit['zona'] : '');
    field_input('estado', 'Estado', $edit ? $edit['estado'] : 'activo');
    field_textarea('servicios', 'Servicios', $edit ? $edit['servicios'] : '', 3);
    field_textarea('tarifas', 'Tarifas', $edit ? $edit['tarifas'] : '', 3);

    echo '<div class="full"><button class="btn-primary">Guardar bot</button></div>';
    echo '</form>';

    if ($edit) {
        render_bot_leads_panel($edit);
    }

    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay bots todavía.</div>';
    } else {
        $cidx = clientes_index();
        $items = sort_desc_by_key($items, 'created_at');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Bot</th><th>Teléfono</th><th>WAHA</th><th>Clienta actual</th><th>Zona</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($items as $row) {
            $clientName = isset($cidx[$row['cliente_id']]['nombre']) ? $cidx[$row['cliente_id']]['nombre'] : 'Sin vincular';
            echo '<tr>';
            echo '<td><strong>' . e($row['nombre_bot']) . '</strong></td>';
            echo '<td>' . e($row['telefono_bot']) . '</td>';
            echo '<td>' . e($row['waha_port']) . '</td>';
            echo '<td>' . e($clientName) . '</td>';
            echo '<td>' . e($row['zona']) . '</td>';
            echo '<td>';
            echo '<a class="mini-link" href="index.php?page=bots&edit=' . e($row['id']) . '">Abrir ficha</a> ';
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar bot?\')">';
            echo '<input type="hidden" name="action" value="delete_bot">';
            echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
            echo '<button class="btn-danger-mini">Borrar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_bot_leads_panel($bot) {
    echo '<hr class="sep">';
    echo '<div class="lead-zone">';
    echo '<h2>Leads históricos de este bot</h2>';

    $from = request_get('bot_leads_from', '');
    $to = request_get('bot_leads_to', '');
    $leads = get_leads_for_bot($bot['id']);
    $leads = filter_rows_between_dates($leads, 'fecha_hora', $from, $to);
    $leads = sort_desc_by_key($leads, 'fecha_hora');
    $totals = lead_totals($leads);

    echo '<form method="get" class="toolbar toolbar-small lead-filter-bar">';
    echo '<input type="hidden" name="page" value="bots">';
    echo '<input type="hidden" name="edit" value="' . e($bot['id']) . '">';
    echo '<div class="field"><label>Desde</label><input type="date" name="bot_leads_from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="bot_leads_to" value="' . e($to) . '"></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
    echo '</form>';

    if (empty($leads)) {
        echo '<div class="empty">No hay leads para este bot con esos filtros.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Precio</th><th>Clienta</th><th>Bot</th><th>Observación</th>';
        echo '</tr></thead><tbody>';
        foreach ($leads as $lead) {
            echo '<tr>';
            echo '<td>' . e(str_replace('T', ' ', $lead['fecha_hora'])) . '</td>';
            echo '<td><span class="money-chip">' . e(euro($lead['precio_lead'])) . '</span></td>';
            echo '<td>' . e($lead['cliente_nombre']) . '</td>';
            echo '<td>' . e($lead['bot_nombre']) . '</td>';
            echo '<td>' . e(isset($lead['observaciones']) ? $lead['observaciones'] : '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<div class="totals-bar">';
    echo '<div><strong>Total leads:</strong> ' . e($totals['count']) . '</div>';
    echo '<div><strong>Total dinero:</strong> <span class="money-chip big">' . e(euro($totals['money'])) . '</span></div>';
    echo '</div>';
    echo '</div>';
}

function render_informes_page() {
    $clientes = storage_read('clientes.json');
    $leads = storage_read('leads.json');
    $interesadas = storage_read('interesadas.json');

    $clienteId = request_get('cliente_id', '');
    $from = request_get('from', date('Y-m-01'));
    $to = request_get('to', date('Y-m-d'));

    $filteredLeads = array();
    foreach ($leads as $lead) {
        if ($clienteId !== '' && (!isset($lead['cliente_id']) || $lead['cliente_id'] !== $clienteId)) continue;
        $filteredLeads[] = $lead;
    }
    $filteredLeads = filter_rows_between_dates($filteredLeads, 'fecha_hora', $from, $to);

    $filteredClientes = array();
    foreach ($clientes as $cliente) {
        if ($clienteId !== '' && $cliente['id'] !== $clienteId) continue;
        $filteredClientes[] = $cliente;
    }
    $filteredClientes = filter_rows_between_dates($filteredClientes, 'fecha_alta', $from, $to);

    $leadTotals = lead_totals($filteredLeads);

    $altaIncome = 0;
    foreach ($filteredClientes as $cliente) {
        $altaIncome += isset($cliente['precio_alta']) ? (float)$cliente['precio_alta'] : 0;
    }

    $nuevas = 0;
    $atendidas = 0;
    $convertidas = 0;
    foreach ($interesadas as $i) {
        if (!isset($i['estado'])) continue;
        if ($i['estado'] === 'nueva') $nuevas++;
        if ($i['estado'] === 'atendida') $atendidas++;
        if ($i['estado'] === 'convertida') $convertidas++;
    }

    page_header('Informes', 'Vista global por fechas y por clienta');

    echo '<section class="panel panel-space">';
    echo '<form method="get" class="toolbar">';
    echo '<input type="hidden" name="page" value="informes">';
    echo '<div class="field"><label>Desde</label><input type="date" name="from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="to" value="' . e($to) . '"></div>';
    echo '<div class="field"><label>Clienta</label><select name="cliente_id">';
    echo '<option value="">Todas</option>';
    foreach ($clientes as $cliente) {
        $sel = ($clienteId === $cliente['id']) ? ' selected' : '';
        echo '<option value="' . e($cliente['id']) . '"' . $sel . '>' . e($cliente['nombre']) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Aplicar filtros</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<div class="cards four">';
    dashboard_card('Leads filtrados', $leadTotals['count']);
    dashboard_card('Ingresos leads', euro($leadTotals['money']), true);
    dashboard_card('Altas filtradas', count($filteredClientes));
    dashboard_card('Ingresos altas', euro($altaIncome), true);
    echo '</div>';

    echo '<div class="cards four">';
    dashboard_card('Interesadas nuevas', $nuevas);
    dashboard_card('Interesadas atendidas', $atendidas);
    dashboard_card('Interesadas convertidas', $convertidas);
    dashboard_card('Total negocio', euro($leadTotals['money'] + $altaIncome), true);
    echo '</div>';
}

function field_input($name, $label, $value = '', $required = false, $type = 'text') {
    echo '<div class="field">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '>';
    echo '</div>';
}

function field_textarea($name, $label, $value = '', $rows = 4) {
    echo '<div class="field full">';
    echo '<label>' . e($label) . '</label>';
    echo '<textarea name="' . e($name) . '" rows="' . e($rows) . '">' . e($value) . '</textarea>';
    echo '</div>';
}

function field_select_clienta($name, $label, $clientes, $selected = '') {
    echo '<div class="field">';
    echo '<label>' . e($label) . '</label>';
    echo '<select name="' . e($name) . '">';
    echo '<option value="">-- Selecciona clienta --</option>';
    foreach ($clientes as $cliente) {
        $sel = ($selected === $cliente['id']) ? ' selected' : '';
        echo '<option value="' . e($cliente['id']) . '"' . $sel . '>' . e($cliente['nombre']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}
PHP;

$files[$projectName . '/assets/style.css'] = <<<'CSS'
:root{
  --bg:#08111f;
  --panel:#111c2d;
  --text:#edf2f7;
  --muted:#9fb0c7;
  --line:#26384f;
  --accent:#e83e8c;
  --accent2:#8a5cf6;
  --danger:#ef4444;
  --ok:#22c55e;
  --warn:#f59e0b;
  --money:#10b981;
}
*{box-sizing:border-box}
body{
  margin:0;
  font-family:Arial,Helvetica,sans-serif;
  background:linear-gradient(180deg,#08111f,#02060d);
  color:var(--text);
}
a{text-decoration:none;color:#fff}
.layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.sidebar{background:#060c16;border-right:1px solid var(--line);padding:22px}
.brand{font-size:28px;font-weight:bold;margin-bottom:6px}
.brand span{color:var(--accent)}
.userbox{color:var(--muted);margin-bottom:18px;font-size:14px}
.nav a{display:block;padding:12px 14px;margin-bottom:8px;border-radius:12px;background:transparent;color:var(--text);border:1px solid transparent}
.nav a:hover,.nav a.active{background:rgba(232,62,140,.12);border-color:rgba(232,62,140,.25)}
.main{padding:22px}
.page-head{margin-bottom:18px}
.page-head h1{margin:0 0 6px;font-size:30px}
.page-head p{margin:0;color:var(--muted)}
.cards{display:grid;gap:16px;margin-bottom:16px}
.cards.two{grid-template-columns:repeat(2,minmax(0,1fr))}
.cards.three{grid-template-columns:repeat(3,minmax(0,1fr))}
.cards.four{grid-template-columns:repeat(4,minmax(0,1fr))}
.panel{background:rgba(17,28,45,.96);border:1px solid var(--line);border-radius:18px;padding:18px;box-shadow:0 15px 30px rgba(0,0,0,.20)}
.panel-space{margin-bottom:16px}
.panel h2{margin-top:0;font-size:18px}
.panel-highlight-success{box-shadow:0 0 0 1px rgba(16,185,129,.25),0 18px 40px rgba(16,185,129,.08)}
.big-msg{font-size:14px;margin-bottom:14px}
.stat{display:flex;flex-direction:column;justify-content:center;min-height:120px}
.stat-label{color:var(--muted);font-size:13px;margin-bottom:12px}
.stat-value{font-size:30px;font-weight:bold}
.stat-value.money{font-size:26px}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.field{display:flex;flex-direction:column}
.field.full,.full{grid-column:1 / -1}
.field label{font-size:13px;color:#d7dfeb;margin-bottom:6px}
input,select,textarea,button{width:100%;border-radius:12px;border:1px solid #30455f;background:#0b1422;color:var(--text);padding:11px 12px}
textarea{resize:vertical;min-height:90px}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent2));border:none;font-weight:bold;cursor:pointer}
.btn-big{padding:16px 18px;font-size:16px}
.btn-danger-mini{background:#231014;border:1px solid #5a232b;color:#ffd9df;padding:6px 10px;width:auto;cursor:pointer}
.btn-warning-mini{background:#2a1e0a;border:1px solid #7a5611;color:#ffe7b3;padding:6px 10px;width:auto;cursor:pointer}
.btn-ok-mini{background:#102517;border:1px solid #1f6a33;color:#d9ffe2;padding:6px 10px;width:auto;cursor:pointer}
.mini-link,.success-link{display:inline-block;padding:7px 10px;background:#18283d;border:1px solid #314862;border-radius:10px;color:#fff;font-size:12px}
.success-link{background:#102517;border-color:#1f6a33}
.inline-form{display:inline-block}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--line);vertical-align:top;font-size:14px}
th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#c5d0de}
.muted{color:var(--muted);font-size:12px}
.empty{color:var(--muted);padding:14px 0}
.flash{padding:14px 16px;border-radius:14px;margin-bottom:16px;font-weight:bold}
.flash-ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.22);color:#c9f7d7}
.flash-error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.22);color:#ffd8d8}
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.login-card{width:100%;max-width:420px;background:rgba(17,28,45,.96);border:1px solid var(--line);border-radius:18px;padding:28px}
.center{text-align:center}
.login-help{text-align:center;color:var(--muted);margin-top:14px;font-size:13px}
.toolbar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.toolbar-small{grid-template-columns:repeat(3,minmax(0,1fr))}
.field-btn{align-self:end}
canvas{width:100% !important;height:300px !important}
.pill{display:inline-block;padding:5px 10px;border-radius:999px;font-size:12px;border:1px solid #344960;background:#162334}
.state-nueva{background:#14253a;color:#cfe5ff}
.state-atendida{background:#2a2210;color:#ffeab8}
.state-convertida{background:#132818;color:#d7ffe0}
.state-descartada{background:#2a1515;color:#ffd7d7}
.state-alta{background:#132818;color:#d7ffe0}
.state-baja{background:#2a1515;color:#ffd7d7}
.row-state-nueva td{background:rgba(59,130,246,.06)}
.row-state-atendida td{background:rgba(245,158,11,.08)}
.row-state-convertida td{background:rgba(16,185,129,.08)}
.row-state-descartada td{background:rgba(239,68,68,.07)}
.sep{border:none;border-top:1px solid var(--line);margin:18px 0}
.lead-zone{margin-top:6px}
.money-callout{
  background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(34,197,94,.10));
  border:1px solid rgba(16,185,129,.35);
  border-radius:18px;
  padding:18px;
  margin-bottom:16px
}
.money-title{font-size:18px;font-weight:bold;margin-bottom:12px;color:#dcfce7}
.lead-quick-inline{display:grid;grid-template-columns:120px 1fr auto;gap:10px;align-items:center}
.money-input{
  font-size:26px;
  font-weight:bold;
  text-align:center;
  background:#07160f;
  border:2px solid rgba(16,185,129,.45);
  color:#d1fae5
}
.money-note{background:#08111f}
.btn-money{
  background:linear-gradient(135deg,#16a34a,#10b981);
  border:none;
  font-size:18px;
  font-weight:bold;
  padding:18px 20px;
  box-shadow:0 12px 28px rgba(16,185,129,.25);
  cursor:pointer
}
.money-chip{
  display:inline-block;
  padding:6px 10px;
  border-radius:999px;
  background:rgba(16,185,129,.14);
  border:1px solid rgba(16,185,129,.30);
  color:#d1fae5;
  font-weight:bold
}
.money-chip.big{font-size:16px;padding:8px 12px}
.totals-bar{
  display:flex;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  margin-top:14px;
  padding:14px 16px;
  border-radius:14px;
  background:#0c1626;
  border:1px solid var(--line)
}
.info-strip{
  margin-top:14px;
  padding:12px 14px;
  border-radius:12px;
  background:#0c1626;
  border:1px solid var(--line);
  color:#d9e2ef
}
.mini-actions-bar{margin-top:12px}
.action-stack{display:flex;flex-wrap:wrap;gap:6px}
.floating-toast{
  position:fixed;
  right:18px;
  top:18px;
  z-index:9999;
  min-width:260px;
  max-width:420px;
  opacity:0;
  transform:translateY(-10px);
  pointer-events:none;
  transition:.28s ease;
  padding:14px 16px;
  border-radius:14px;
  font-weight:bold;
  background:rgba(16,185,129,.96);
  color:#06280f;
  box-shadow:0 14px 40px rgba(0,0,0,.24)
}
.floating-toast.show{opacity:1;transform:translateY(0)}
.money-rain{
  pointer-events:none;
  position:fixed;
  inset:0;
  z-index:9998;
  overflow:hidden
}
.euro-drop{
  position:absolute;
  top:-40px;
  font-size:30px;
  animation:fall 2.8s linear forwards;
  opacity:.95
}
@keyframes fall{
  0%{transform:translateY(-20px) rotate(0deg);opacity:0}
  10%{opacity:1}
  100%{transform:translateY(110vh) rotate(360deg);opacity:0}
}
@media (max-width:1100px){
  .layout{grid-template-columns:1fr}
  .cards.two,.cards.three,.cards.four,.form-grid,.toolbar,.toolbar-small,.lead-quick-inline{grid-template-columns:1fr}
}
CSS;

$files[$projectName . '/assets/app.js'] = <<<'JS'
(function () {
    function showToast(message, type) {
        var el = document.getElementById('floatingToast');
        if (!el || !message) return;
        el.textContent = message;
        el.style.background = type === 'error' ? 'rgba(239,68,68,.96)' : 'rgba(16,185,129,.96)';
        el.style.color = type === 'error' ? '#fff' : '#06280f';
        el.classList.add('show');
        setTimeout(function () {
            el.classList.remove('show');
        }, 3200);
    }

    function euroRain() {
        var wrap = document.getElementById('moneyRain');
        if (!wrap) return;
        wrap.innerHTML = '';
        for (var i = 0; i < 28; i++) {
            var d = document.createElement('div');
            d.className = 'euro-drop';
            d.textContent = '€';
            d.style.left = Math.floor(Math.random() * 100) + 'vw';
            d.style.animationDelay = (Math.random() * 0.7) + 's';
            d.style.fontSize = (22 + Math.random() * 24) + 'px';
            wrap.appendChild(d);
        }
        setTimeout(function () {
            wrap.innerHTML = '';
        }, 3600);
    }

    window.confirmLeadSubmit = function (form) {
        var priceInput = form.querySelector('input[name="precio_lead"]');
        var amount = priceInput ? priceInput.value : '10';
        return confirm('¿Seguro que quieres confirmar este lead de ' + amount + '€?');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var flash = document.querySelector('.flash');
        if (flash) {
            var message = flash.textContent || '';
            var type = flash.classList.contains('flash-error') ? 'error' : 'ok';
            showToast(message, type);
            var fx = flash.getAttribute('data-fx') || '';
            if (fx === 'money' || fx === 'celebrate') {
                euroRain();
            }
            if (fx === 'motivate') {
                setTimeout(function () {
                    showToast('Buen trabajo. Siguiente paso: convertirla.', 'ok');
                }, 700);
            }
        }
    });
})();
JS;

$files[$projectName . '/README.txt'] = <<<'TXT'
LAMAMI CRM - PHP 7.4 + JSON

ACCESO
Usuario: nuria
Contraseña: josue

FLUJO
Interesada -> Atendida -> Convertida -> Clienta

REGLAS
- No se pueden crear clientas directamente
- Las clientas solo se crean desde interesadas
- Los leads se registran en la ficha de la clienta
- Solo se pueden registrar leads si la clienta tiene bot vinculado
- Cada lead guarda también el bot histórico de ese momento
- Las clientas pueden darse de baja

SECCIONES
- Dashboard
- Interesadas
- Clientas
- Bots
- Informes

NOTA
La carpeta /data debe tener permisos de escritura
TXT;

$installed = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $target = $baseDir . DIRECTORY_SEPARATOR . $projectName;

    if (is_dir($target)) {
        rrmdir($target);
    }
    mkdir($target, 0775, true);

    foreach ($files as $relative => $content) {
        write_file($baseDir . DIRECTORY_SEPARATOR . $relative, $content);
    }

    $installed = true;
    $message = 'Proyecto creado correctamente en: ' . $projectName;
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Instalador LaMami CRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#0b1220;color:#eef2f7;margin:0;padding:24px}
        .box{max-width:900px;margin:0 auto;background:#111827;border:1px solid #243041;border-radius:18px;padding:24px}
        h1{margin-top:0}
        .ok{background:#122b1c;border:1px solid #234e34;color:#d7ffe6;padding:12px 14px;border-radius:12px;margin:16px 0}
        .info{background:#101a2a;border:1px solid #243041;color:#d8e1ef;padding:12px 14px;border-radius:12px;margin:16px 0}
        button{background:linear-gradient(135deg,#ec4899,#8b5cf6);color:#fff;border:none;padding:14px 18px;border-radius:12px;font-weight:bold;cursor:pointer}
        code{background:#0b1220;padding:2px 6px;border-radius:6px}
        ul{line-height:1.7}
    </style>
</head>
<body>
    <div class="box">
        <h1>Instalador LaMami CRM</h1>
        <div class="info">
            Este instalador creará una carpeta <code>lamami_crm</code> con el sistema completo en PHP 7.4 + JSON.
        </div>

        <?php if ($installed): ?>
            <div class="ok"><?php echo h($message); ?></div>
            <ul>
                <li>Entra ahora en: <code><?php echo h($projectName); ?>/index.php</code></li>
                <li>Usuario: <code>nuria</code></li>
                <li>Contraseña: <code>josue</code></li>
                <li>Si da problemas al guardar, revisa permisos de la carpeta <code>data</code></li>
            </ul>
        <?php else: ?>
            <ul>
                <li>Login fijo: <code>nuria / josue</code></li>
                <li>Leads integrados en la ficha de la clienta</li>
                <li>Listado de leads en ficha de clienta y ficha de bot</li>
                <li>Efecto visual monetario al confirmar lead</li>
                <li>Interesadas con flujo comercial visual</li>
            </ul>
            <form method="post">
                <button type="submit" name="install" value="1">Instalar proyecto</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
