<?php
/**
 * personal_wasap_ingest.php — Lógica compartida de persistencia del chat personal.
 *
 * Extrae de personal_wasap_webhook.php los helpers de store y la ingestión de
 * mensajes, para que tanto el webhook de WAHA (personal_wasap_webhook.php) como
 * el de Evolution (personal_wasap_webhook_evo.php) persistan igual en
 * data/personal_wasap_data.json sin duplicar lógica.
 */

declare(strict_types=1);

if (!function_exists('wasap_ingest_store_path')) {
    function wasap_ingest_store_path(): string
    {
        return dirname(__DIR__) . '/data/personal_wasap_data.json';
    }
}

if (!function_exists('wasap_ingest_log')) {
    function wasap_ingest_log(string $action, array $data): void
    {
        $logPath = dirname(__DIR__) . '/data/personal_wasap_debug.log';
        $dir = dirname($logPath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $entry = date('Y-m-d H:i:s') . ' | ' . $action . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($logPath, $entry . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('wasap_ingest_store_read')) {
    function wasap_ingest_store_read(): array
    {
        $path = wasap_ingest_store_path();
        if (!file_exists($path)) return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
        $raw = @file_get_contents($path);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    }
}

if (!function_exists('wasap_ingest_store_write')) {
    function wasap_ingest_store_write(array $data): void
    {
        $path = wasap_ingest_store_path();
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $fh = @fopen($path, 'c+');
        if (!$fh) return;
        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return;
        }
        $raw = '';
        while (!feof($fh)) {
            $chunk = fread($fh, 8192);
            if ($chunk === false) break;
            $raw .= $chunk;
        }
        $existing = json_decode($raw, true);
        $existing = is_array($existing) ? $existing : [];

        if (!isset($existing['chats'])) $existing['chats'] = [];
        if (!isset($existing['contacts_index'])) $existing['contacts_index'] = [];
        if (!isset($existing['learning'])) $existing['learning'] = [];
        if (!isset($existing['meta'])) $existing['meta'] = [];

        // Merge chats por chatId; mensajes por ID
        if (isset($data['chats'])) {
            foreach ($data['chats'] as $chatId => $chatData) {
                if (!isset($existing['chats'][$chatId])) {
                    $existing['chats'][$chatId] = $chatData;
                } else {
                    $existingIds = [];
                    foreach ($existing['chats'][$chatId]['messages'] ?? [] as $em) {
                        $existingIds[$em['id'] ?? ''] = true;
                    }
                    foreach ($chatData['messages'] ?? [] as $nm) {
                        $nid = (string)($nm['id'] ?? '');
                        if ($nid !== '' && isset($existingIds[$nid])) continue;
                        $existing['chats'][$chatId]['messages'][] = $nm;
                    }
                    $existing['chats'][$chatId]['last_message_at'] = $chatData['last_message_at'] ?? ($existing['chats'][$chatId]['last_message_at'] ?? '');
                    $existing['chats'][$chatId]['unread_count'] = $chatData['unread_count'] ?? ($existing['chats'][$chatId]['unread_count'] ?? 0);
                    if (!empty($chatData['contact_name']) && empty($existing['chats'][$chatId]['contact_name'])) {
                        $existing['chats'][$chatId]['contact_name'] = $chatData['contact_name'];
                    }
                    $existing['chats'][$chatId]['contact_phone'] = $chatData['contact_phone'] ?? ($existing['chats'][$chatId]['contact_phone'] ?? '');
                }
            }
        }
        if (isset($data['contacts_index'])) $existing['contacts_index'] = array_merge($existing['contacts_index'], $data['contacts_index']);
        if (isset($data['learning'])) {
            foreach (['daily_stats' => [], 'pending_classification' => []] as $lk => $ld) {
                if (isset($data['learning'][$lk])) {
                    $existing['learning'][$lk] = $data['learning'][$lk];
                }
            }
        }
        if (isset($data['meta'])) $existing['meta'] = $data['meta'];

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

if (!function_exists('wasap_ingest_digits')) {
    function wasap_ingest_digits(string $value): string
    {
        $local = explode('@', $value)[0];
        $local = explode(':', $local)[0];
        return preg_replace('/\D+/', '', $local) ?: '';
    }
}

/**
 * Ingestiona un mensaje normalizado en el store personal.
 *
 * @param array<string,mixed> $m campos:
 *   - chatId, peerPhone, direction ('in'|'out'), fromMe(bool), messageId,
 *     text (string), pushName (string), isGroup(bool), media(?array)
 * @return array{ok:bool,chat_id:string,direction:string,duplicate:bool}
 */
if (!function_exists('wasap_ingest_message')) {
    function wasap_ingest_message(array $m): array
    {
        $chatId = (string)($m['chatId'] ?? '');
        $peerPhone = (string)($m['peerPhone'] ?? '');
        $direction = (string)($m['direction'] ?? 'in');
        $fromMe = (bool)($m['fromMe'] ?? false);
        $messageId = (string)($m['messageId'] ?? ('msg_' . bin2hex(random_bytes(8))));
        $msgText = trim((string)($m['text'] ?? ''));
        $pushName = (string)($m['pushName'] ?? '');
        $isGroup = (bool)($m['isGroup'] ?? false);
        $media = (isset($m['media']) && is_array($m['media'])) ? $m['media'] : null;

        $store = wasap_ingest_store_read();
        $now = date('c');
        $nowDate = date('Y-m-d');

        if (!isset($store['chats'][$chatId])) {
            $store['chats'][$chatId] = [
                'contact_name' => '',
                'contact_phone' => $peerPhone,
                'last_message_at' => $now,
                'unread_count' => 0,
                'messages' => [],
            ];
        }
        $chat = &$store['chats'][$chatId];

        if (!$fromMe && $pushName !== '' && ($chat['contact_name'] ?? '') === '') {
            $chat['contact_name'] = $pushName;
        }

        $msgRecord = [
            'id' => $messageId,
            'direction' => $direction,
            'from_me' => $fromMe,
            'text' => $msgText !== '' ? $msgText : '📎 Mensaje',
            'ts' => $now,
            'read' => $fromMe,
        ];
        if ($media !== null) {
            $msgRecord['media'] = $media;
        }

        $duplicate = false;
        foreach ($chat['messages'] as $existing) {
            if (($existing['id'] ?? '') === $messageId) {
                $duplicate = true;
                break;
            }
        }

        if (!$duplicate) {
            $chat['messages'][] = $msgRecord;
            $chat['last_message_at'] = $now;
            if (!$fromMe) {
                $chat['unread_count'] = ($chat['unread_count'] ?? 0) + 1;
            }
            if (count($chat['messages']) > 500) {
                $chat['messages'] = array_slice($chat['messages'], -500);
            }

            $contactKey = $peerPhone;
            if (!isset($store['contacts_index'][$contactKey])) {
                $store['contacts_index'][$contactKey] = [
                    'name' => $chat['contact_name'] ?? '',
                    'first_seen' => $nowDate,
                    'last_seen' => $nowDate,
                    'chats_with' => 1,
                    'total_messages' => 1,
                ];
            } else {
                $store['contacts_index'][$contactKey]['last_seen'] = $nowDate;
                $store['contacts_index'][$contactKey]['total_messages'] = ($store['contacts_index'][$contactKey]['total_messages'] ?? 0) + 1;
                if (($chat['contact_name'] ?? '') !== '' && ($store['contacts_index'][$contactKey]['name'] ?? '') === '') {
                    $store['contacts_index'][$contactKey]['name'] = $chat['contact_name'];
                }
            }

            if (!isset($store['learning']['daily_stats'])) $store['learning']['daily_stats'] = [];
            if (!isset($store['learning']['daily_stats'][$nowDate])) {
                $store['learning']['daily_stats'][$nowDate] = ['messages_sent' => 0, 'messages_received' => 0, 'contacts_talked_to' => []];
            }
            if ($fromMe) {
                $store['learning']['daily_stats'][$nowDate]['messages_sent']++;
            } else {
                $store['learning']['daily_stats'][$nowDate]['messages_received']++;
            }
            if (!in_array($peerPhone, $store['learning']['daily_stats'][$nowDate]['contacts_talked_to'])) {
                $store['learning']['daily_stats'][$nowDate]['contacts_talked_to'][] = $peerPhone;
            }

            if (!isset($store['learning']['pending_classification'])) $store['learning']['pending_classification'] = [];
            if ($msgText !== '' && mb_strlen($msgText) > 15) {
                $store['learning']['pending_classification'][] = [
                    'message_id' => $messageId,
                    'chat_id' => $chatId,
                    'direction' => $direction,
                    'text' => $msgText,
                    'ts' => $now,
                ];
                if (count($store['learning']['pending_classification']) > 50) {
                    $store['learning']['pending_classification'] = array_slice($store['learning']['pending_classification'], -50);
                }
            }

            wasap_ingest_log('message_persisted', [
                'chat_id' => $chatId,
                'peer_phone' => $peerPhone,
                'direction' => $direction,
                'from_me' => $fromMe,
                'text_preview' => mb_substr($msgText, 0, 200),
                'message_id' => $messageId,
                'is_group' => $isGroup,
                'media' => $media !== null ? ($media['type'] ?? '') : null,
            ]);
        } else {
            wasap_ingest_log('duplicate_skipped', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'direction' => $direction,
            ]);
        }

        wasap_ingest_store_write($store);

        return ['ok' => true, 'chat_id' => $chatId, 'direction' => $direction, 'duplicate' => $duplicate];
    }
}
