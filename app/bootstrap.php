<?php
date_default_timezone_set('Europe/Madrid');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', BASE_PATH . '/data');

function bootstrap_runtime_dir_ensure($path) {
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
    return is_dir($path);
}

function bootstrap_php_error_log_path() {
    bootstrap_runtime_dir_ensure(DATA_PATH);
    return DATA_PATH . '/php_errors.log';
}

function bootstrap_session_storage_prepare() {
    $sessionPath = trim((string)ini_get('session.save_path'));
    $sessionWritable = ($sessionPath !== '' && @is_dir($sessionPath) && @is_writable($sessionPath));
    if ($sessionWritable) {
        return;
    }
    $fallbackDir = DATA_PATH . '/sessions';
    if (bootstrap_runtime_dir_ensure($fallbackDir) && @is_writable($fallbackDir)) {
        @session_save_path($fallbackDir);
    }
}

function bootstrap_runtime_log($message) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . trim((string)$message) . "\n";
    @file_put_contents(bootstrap_php_error_log_path(), $line, FILE_APPEND | LOCK_EX);
}

function bootstrap_runtime_log_exception($context, $e) {
    $context = trim((string)$context);
    if (!$e instanceof Throwable) {
        bootstrap_runtime_log(($context !== '' ? $context . ' | ' : '') . 'Excepción no válida.');
        return;
    }
    $lines = array();
    $lines[] = ($context !== '' ? $context . ' | ' : '') . get_class($e) . ': ' . $e->getMessage();
    $lines[] = 'Archivo: ' . $e->getFile() . ':' . $e->getLine();
    $trace = trim((string)$e->getTraceAsString());
    if ($trace !== '') {
        $lines[] = 'Trace: ' . $trace;
    }
    bootstrap_runtime_log(implode(' | ', $lines));
}

function bootstrap_runtime_render_fatal($title, $message) {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        @header('Content-Type: text/plain; charset=UTF-8');
    }
    echo $title . "\n" . $message . "\n";
}

bootstrap_runtime_dir_ensure(DATA_PATH);
bootstrap_session_storage_prepare();
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@ini_set('log_errors', '1');
@ini_set('error_log', bootstrap_php_error_log_path());
error_reporting(E_ALL);

set_exception_handler(function($e) {
    bootstrap_runtime_log_exception('Uncaught exception', $e);
    $message = ($e instanceof Throwable) ? ($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()) : 'Excepción no controlada.';
    bootstrap_runtime_render_fatal('Error PHP no controlado', $message);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }
    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    $message = trim((string)($error['message'] ?? ''));
    $file = trim((string)($error['file'] ?? ''));
    $line = (int)($error['line'] ?? 0);
    bootstrap_runtime_log('Fatal shutdown | ' . $message . ' | ' . $file . ':' . $line);
    bootstrap_runtime_render_fatal('Fatal PHP', $message . ' en ' . $file . ':' . $line);
});

session_start();

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/evolution/transport.php';
require_once APP_PATH . '/evolution/config.php';
require_once APP_PATH . '/evolution/transcribe.php';
require_once APP_PATH . '/db.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/avisos.php';
require_once APP_PATH . '/auth.php';
require_once APP_PATH . '/voice.php';
require_once APP_PATH . '/autotube_bridge.php';
require_once APP_PATH . '/youtube.php';
require_once APP_PATH . '/publicista.php';
require_once APP_PATH . '/publicista_girlsconf.php';
require_once APP_PATH . '/comercial_knowledge.php';
require_once APP_PATH . '/compartir_media.php';
require_once APP_PATH . '/comercial_anti_spam.php';
require_once APP_PATH . '/comercial_humanize.php';
require_once APP_PATH . '/comercial_agent.php';
require_once APP_PATH . '/comercial_agent_critic.php';
require_once APP_PATH . '/comercial.php';
require_once APP_PATH . '/actions.php';
require_once APP_PATH . '/bot_templates.php';
require_once APP_PATH . '/views.php';

bootstrap_storage();
