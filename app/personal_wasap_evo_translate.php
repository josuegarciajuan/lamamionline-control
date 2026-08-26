<?php
/**
 * personal_wasap_evo_translate.php — Traductor de mensajes de Evolution al formato
 * del chat personal. Lo usan tanto el webhook (personal_wasap_webhook_evo.php)
 * como el sync de salientes nativos (personal_wasap_api.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/evolution/config.php';
require_once __DIR__ . '/evolution/transcribe.php';
require_once __DIR__ . '/personal_wasap_ingest.php';

if (!function_exists('personal_wasap_evo_text')) {
    /** @param array<string,mixed> $message */
    function personal_wasap_evo_text(array $message): string
    {
        foreach (['conversation'] as $k) {
            if (isset($message[$k]) && is_string($message[$k])) return $message[$k];
        }
        foreach (['extendedTextMessage', 'buttonResponseMessage', 'listResponseMessage'] as $k) {
            if (isset($message[$k]) && is_array($message[$k])) {
                $t = $message[$k]['text'] ?? $message[$k]['selectedButtonText'] ?? $message[$k]['title'] ?? '';
                if (is_string($t) && $t !== '') return $t;
            }
        }
        return '';
    }
}

if (!function_exists('personal_wasap_evo_translate')) {
    /**
     * Traduce un mensaje de Evolution al formato de wasap_ingest_message().
     * @param array<string,mixed> $msg
     * @return array<string,mixed>|null
     */
    function personal_wasap_evo_translate(array $msg): ?array
    {
        $key = $msg['key'] ?? [];
        $remoteJid = (string)($key['remoteJid'] ?? '');
        $fromMe = (bool)($key['fromMe'] ?? false);
        $messageId = (string)($key['id'] ?? '');
        $message = $msg['message'] ?? [];
        if (!is_array($message)) $message = [];
        $pushName = (string)($msg['pushName'] ?? '');

        if ($remoteJid === '') {
            return null;
        }
        $isGroup = str_contains($remoteJid, '@g.us');
        $peerPhone = wasap_ingest_digits($remoteJid);
        if ($peerPhone === '') {
            return null;
        }

        $text = personal_wasap_evo_text($message);
        $media = EvolutionApi::mediaUrlFromMessage($message);

        // Transcripción de audio (faster-whisper) — solo media de Evolution (MinIO)
        if ($media !== null && ($media['type'] ?? '') === 'audio') {
            $trans = whatsapp_transcribe_media($media);
            if ($trans !== null) {
                $media['transcription'] = $trans;
            }
        }

        $direction = $fromMe ? 'out' : 'in';
        $chatId = $peerPhone . ($isGroup ? '@g.us' : '@c.us');

        return [
            'chatId' => $chatId,
            'peerPhone' => $peerPhone,
            'direction' => $direction,
            'fromMe' => $fromMe,
            'messageId' => $messageId,
            'text' => $text,
            'pushName' => $pushName,
            'isGroup' => $isGroup,
            'media' => $media,
        ];
    }
}
