<?php
/**
 * tools/fix_wasap_contact_names.php — one-shot de limpieza para el WhatsApp Personal de Josué.
 *
 * Problema: el webhook guardaba `contact_name = "Josué"` (el pushName propio de Josué)
 * en las conversaciones que él abría con un mensaje saliente (fromMe=true).
 * Este script resetea a "" esos nombres para que el listado muestre el teléfono
 * y el nombre real se auto-rellene con el próximo mensaje entrante.
 *
 * Idempotente: puede ejecutarse varias veces sin daño. Crea backup antes de escribir.
 *
 * Uso:
 *   php tools/fix_wasap_contact_names.php [--dry-run]
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv, true);

$storePath = __DIR__ . '/../data/personal_wasap_data.json';

if (!file_exists($storePath)) {
    fwrite(STDOUT, "No existe {$storePath}; nada que limpiar.\n");
    exit(0);
}

$fh = fopen($storePath, 'c+');
if (!$fh) {
    fwrite(STDERR, "No se pudo abrir {$storePath}\n");
    exit(1);
}

if (!flock($fh, LOCK_EX)) {
    fclose($fh);
    fwrite(STDERR, "No se pudo bloquear {$storePath}\n");
    exit(1);
}

$raw = '';
while (!feof($fh)) {
    $chunk = fread($fh, 8192);
    if ($chunk === false) break;
    $raw .= $chunk;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    flock($fh, LOCK_UN);
    fclose($fh);
    fwrite(STDERR, "JSON inválido en {$storePath}; abortando sin tocar nada.\n");
    exit(1);
}

// Asegurar estructura
if (!isset($data['chats'])) $data['chats'] = [];
if (!isset($data['contacts_index'])) $data['contacts_index'] = [];

$ownName = 'Josué';

$clearedChats = 0;
foreach ($data['chats'] as $chatId => &$chat) {
    if (trim((string)($chat['contact_name'] ?? '')) === $ownName) {
        $chat['contact_name'] = '';
        $clearedChats++;
    }
}
unset($chat);

$clearedIndex = 0;
foreach ($data['contacts_index'] as $key => &$entry) {
    if (trim((string)($entry['name'] ?? '')) === $ownName) {
        $entry['name'] = '';
        $clearedIndex++;
    }
}
unset($entry);

fwrite(STDOUT, "Chats con contact_name \"{$ownName}\" a limpiar: {$clearedChats}\n");
fwrite(STDOUT, "Entradas contacts_index con name \"{$ownName}\" a limpiar: {$clearedIndex}\n");

if ($clearedChats === 0 && $clearedIndex === 0) {
    flock($fh, LOCK_UN);
    fclose($fh);
    fwrite(STDOUT, "Nada que limpiar.\n");
    exit(0);
}

if ($dryRun) {
    flock($fh, LOCK_UN);
    fclose($fh);
    fwrite(STDOUT, "[dry-run] No se ha modificado el archivo.\n");
    exit(0);
}

// Backup de seguridad antes de escribir
$backupPath = $storePath . '.bak.' . date('Ymd_His');
if (@copy($storePath, $backupPath)) {
    fwrite(STDOUT, "Backup creado: {$backupPath}\n");
} else {
    flock($fh, LOCK_UN);
    fclose($fh);
    fwrite(STDERR, "No se pudo crear backup; abortando sin escribir.\n");
    exit(1);
}

$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false) {
    flock($fh, LOCK_UN);
    fclose($fh);
    fwrite(STDERR, "json_encode falló; abortando sin escribir.\n");
    exit(1);
}

ftruncate($fh, 0);
rewind($fh);
fwrite($fh, $json);
fflush($fh);

flock($fh, LOCK_UN);
fclose($fh);

fwrite(STDOUT, "Limpieza aplicada correctamente.\n");
