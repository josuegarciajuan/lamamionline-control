<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * MessageExtractor — extracts structured fields from a WAHA webhook payload.
 *
 * Extracts:
 *  - message_text:   the readable text content
 *  - message_type:   'text', 'audio', 'image', or '' (fallback to 'text')
 *  - is_audio_i:     1 if the message is audio, 0 otherwise
 *  - is_image_i:     1 if the message is image-only (no caption), 0 otherwise
 *  - from_phone:     sender phone number (digits only, no @c.us suffix)
 *  - message_id:     WAHA message ID (wamid)
 *  - timestamp:      epoch timestamp
 *
 * For audio messages, message_text is set to the 'audio_placeholder' config value.
 * For messages with no text, message_text is set to the 'no_text_placeholder' config value.
 *
 * Pattern: based on "Extract WA Text" node in bot.json (pickTextWA, normalizePhone,
 * detectAudioFrom, detectImageFrom functions).
 */
final readonly class MessageExtractor implements PipelineStageInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {}

    public function name(): string
    {
        return 'MessageExtractor';
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    public function process(array $ctx): ?array
    {
        try {
            /** @var array<string, mixed>|null $body */
            $body = $ctx['body'] ?? null;
            $body = is_array($body) ? $body : [];

            /** @var array<string, mixed>|null $payload */
            $payload = $body['payload'] ?? null;
            $payload = is_array($payload) ? $payload : [];

            $msg = $this->resolveMessageObject($ctx, $body, $payload);
            $coalescedText = (string) ($ctx['__coalesced_text'] ?? '');

            // ── Extract text ──────────────────────────────────────────
            $text = '';

            if ($coalescedText !== '') {
                $text = $coalescedText;
            } elseif ($msg !== null) {
                $text = $this->pickTextWA($msg);
            }

            // Fallback text extraction chain
            if ($text === '' && isset($payload['body']) && is_string($payload['body'])) {
                $text = $payload['body'];
            }
            if ($text === '' && isset($ctx['message']) && is_string($ctx['message'])) {
                $text = $ctx['message'];
            }
            if ($text === '' && isset($ctx['text']) && is_string($ctx['text'])) {
                $text = $ctx['text'];
            } elseif ($text === '' && isset($ctx['text']['body']) && is_string($ctx['text']['body'])) {
                $text = $ctx['text']['body'];
            }
            if ($text === '' && isset($ctx['body']) && is_string($ctx['body'])) {
                $text = $ctx['body'];
            }

            $text = trim($text);

            // ── Media detection ───────────────────────────────────────
            $isAudio = $this->detectAudio($ctx, $msg, $payload);
            $isImage = $this->detectImage($ctx, $msg, $payload);
            $isLocation = $this->detectLocation($ctx, $msg, $payload);

            // Determine message type
            $messageType = '';

            if (is_array($msg)) {
                $messageType = mb_strtolower((string) ($msg['type'] ?? ''));
            } elseif ($payload !== []) {
                $messageType = mb_strtolower((string) ($payload['type'] ?? ''));
            }

            if ($messageType === '' && $isAudio) {
                $messageType = 'audio';
            }
            if ($messageType === '' && $isImage) {
                $messageType = 'image';
            }
            if ($messageType === '' && $isLocation) {
                $messageType = 'location';
            }
            if ($messageType === '') {
                $messageType = 'text';
            }

            // ── Placeholder substitution ──────────────────────────────
            $transcription = '';
            if (is_array($payload) && isset($payload['transcription']) && is_string($payload['transcription'])) {
                $transcription = trim($payload['transcription']);
            }
            if ($isAudio) {
                if ($transcription !== '') {
                    // La transcripción ES lo que dijo el cliente: el bot lo usa como texto.
                    $text = $transcription;
                } else {
                    $text = (string) $this->config->get(
                        'message_variants.audio_placeholder',
                        '[AUDIO]'
                    );
                }
            } elseif ($isLocation) {
                // El cliente envió su ubicación: usamos un placeholder
                // significativo (no [SIN_TEXTO]) para que el pipeline lo
                // reconozca como petición de ubicación/maps.
                $text = (string) $this->config->get(
                    'message_variants.location_placeholder',
                    '📍 Ubicación'
                );
            } elseif ($text === '' && !$isAudio) {
                $text = (string) $this->config->get(
                    'message_variants.no_text_placeholder',
                    '[SIN_TEXTO]'
                );
            }

            // ── Extract from_phone ────────────────────────────────────
            $fromPhone = $this->extractFromPhone($ctx, $body, $payload, $msg);

            // ── Extract message_id and timestamp ─────────────────────
            $messageId = $this->extractMessageId($payload, $msg);
            $timestamp = $this->extractTimestamp($payload, $msg);

            // ── Populate context ──────────────────────────────────────
            $ctx['message_text'] = $text;
            $ctx['message_type'] = $messageType;
            $ctx['is_audio_i'] = $isAudio ? 1 : 0;
            $ctx['is_image_i'] = $isImage ? 1 : 0;
            $ctx['is_location_i'] = $isLocation ? 1 : 0;
            $ctx['is_audio']   = $isAudio;
            $ctx['is_image']   = $isImage;
            $ctx['is_location'] = $isLocation;
            $ctx['transcription'] = $transcription;
            $ctx['media'] = (isset($payload['media']) && is_array($payload['media'])) ? $payload['media'] : [];
            $ctx['from_phone'] = $fromPhone;
            $ctx['message_id'] = $messageId;
            $ctx['timestamp']  = $timestamp;

            return $ctx;
        } catch (\Throwable) {
            // Never throw — return null to halt pipeline
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Text extraction — port of pickTextWA from bot.json
    // ─────────────────────────────────────────────────────────────────

    /**
     * Extract readable text from a WAHA message object.
     *
     * Handles various WAHA payload structures: text.body, button text,
     * reaction emoji, interactive messages, etc.
     *
     * @param array<string, mixed> $msg
     */
    private function pickTextWA(array $msg): string
    {
        // Standard text
        if (isset($msg['text']['body']) && is_string($msg['text']['body'])) {
            return $msg['text']['body'];
        }

        // Simple text string
        if (isset($msg['text']) && is_string($msg['text'])) {
            return $msg['text'];
        }

        // Button response
        if (isset($msg['button']['text']) && is_string($msg['button']['text'])) {
            return $msg['button']['text'];
        }

        // Reaction emoji
        if (isset($msg['reaction']['emoji']) && is_string($msg['reaction']['emoji'])) {
            return $msg['reaction']['emoji'];
        }

        // Interactive message (list reply, button reply)
        if (isset($msg['interactive']['type']) && is_string($msg['interactive']['type'])) {
            $type = $msg['interactive']['type'];
            $obj  = $msg['interactive'][$type] ?? null;

            if (is_array($obj)) {
                if (isset($obj['title']) && is_string($obj['title'])) {
                    return $obj['title'];
                }
                if (isset($obj['id']) && is_string($obj['id'])) {
                    return $obj['id'];
                }
            }
        }

        // Direct body field
        if (isset($msg['body']) && is_string($msg['body'])) {
            return $msg['body'];
        }

        // Message field
        if (isset($msg['message']) && is_string($msg['message'])) {
            return $msg['message'];
        }

        // Caption (typical for media messages)
        if (isset($msg['caption']) && is_string($msg['caption'])) {
            return $msg['caption'];
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Phone extraction and normalization
    // ─────────────────────────────────────────────────────────────────

    /**
     * Extract and normalize the sender phone number.
     *
     * Strips @c.us, @s.whatsapp.net, @lid suffixes and all non-digit chars.
     *
     * @param array<string, mixed>    $ctx
     * @param array<string, mixed>    $body
     * @param array<string, mixed>    $payload
     * @param array<string, mixed>|null $msg
     */
    private function extractFromPhone(
        array $ctx,
        array $body,
        array $payload,
        ?array $msg
    ): string {
        // Already extracted by a previous stage
        if (isset($ctx['from_phone']) && is_string($ctx['from_phone']) && $ctx['from_phone'] !== '') {
            return $ctx['from_phone'];
        }

        // Primary extraction from the message/payload
        $raw = '';

        if ($msg !== null) {
            $raw = (string) ($msg['from']
                ?? $msg['author']
                ?? $msg['participant']
                ?? '');
        }

        if ($raw === '' && $payload !== []) {
            $raw = (string) ($payload['from']
                ?? $payload['chatId']
                ?? $payload['sender']['id']
                ?? '');
        }

        // GOWS engine: `from` is a LID (e.g. "277476546711679@lid").
        // The real phone number is in _data.Info.SenderAlt (e.g. "34654464023@s.whatsapp.net").
        // Prefer SenderAlt when available, as it contains the actual E.164 phone number.
        $dataInfo = [];
        if (isset($payload['_data']['Info']) && is_array($payload['_data']['Info'])) {
            $dataInfo = $payload['_data']['Info'];
        }
        $senderAlt = (string) ($dataInfo['SenderAlt'] ?? '');
        if ($senderAlt !== '') {
            $raw = $senderAlt;
        }

        // Fallback: contacts array (WhatsApp Business API style)
        if ($raw === '' && isset($ctx['contacts'][0])) {
            $contact = $ctx['contacts'][0];

            if (is_array($contact)) {
                $raw = (string) ($contact['wa_id'] ?? $contact['id'] ?? '');
            }
        }

        // Fallback: query-style payload
        if ($raw === '' && isset($ctx['query']['from'])) {
            $raw = (string) $ctx['query']['from'];
        }

        // Raw from field on context
        if ($raw === '' && isset($ctx['from'])) {
            $raw = (string) $ctx['from'];
        }

        // Fallback: body.from
        if ($raw === '' && isset($body['from'])) {
            $raw = (string) $body['from'];
        }

        return $this->normalizePhone($raw);
    }

    /**
     * Normalize a phone number — strips all non-digit characters.
     *
     * Also removes known WhatsApp suffixes like @c.us, @lid, @s.whatsapp.net.
     */
    private function normalizePhone(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        // Strip known WA suffixes
        $clean = (string) preg_replace('/@(c\.us|lid|s\.whatsapp\.net|g\.us)/i', '', $raw);

        // Keep only digits
        $digits = (string) preg_replace('/[^0-9]/', '', $clean);

        return $digits;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Message ID and timestamp extraction
    // ─────────────────────────────────────────────────────────────────

    /**
     * Extract message ID from payload or message object.
     *
     * @param array<string, mixed>    $payload
     * @param array<string, mixed>|null $msg
     */
    private function extractMessageId(array $payload, ?array $msg): string
    {
        if ($msg !== null) {
            $id = $msg['id'] ?? $msg['wamid'] ?? null;

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        if ($payload !== []) {
            $id = $payload['id'] ?? $payload['wamid'] ?? null;

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * Extract timestamp from payload or message object.
     *
     * @param array<string, mixed>    $payload
     * @param array<string, mixed>|null $msg
     */
    private function extractTimestamp(array $payload, ?array $msg): int
    {
        if ($msg !== null) {
            $ts = $msg['timestamp'] ?? $msg['ts'] ?? $msg['t'] ?? null;

            if (is_numeric($ts)) {
                return (int) $ts;
            }
        }

        if ($payload !== []) {
            $ts = $payload['timestamp'] ?? $payload['ts'] ?? $payload['t'] ?? null;

            if (is_numeric($ts)) {
                return (int) $ts;
            }
        }

        return time();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Media type detection
    // ─────────────────────────────────────────────────────────────────

    /**
     * Detect whether the message is an audio/voice note.
     *
     * Checks multiple indicators: type field, mime type, hasMedia + ptt flags,
     * struct with audioMessage, and URL patterns (.oga, .ogg).
     *
     * Port of detectAudioFrom() / detectAudioRobust from bot.json.
     *
     * @param array<string, mixed>    $ctx
     * @param array<string, mixed>|null $msg
     * @param array<string, mixed>    $payload
     */
    /** @param array<string, mixed> $ctx @param array<string, mixed>|null $msg @param array<string, mixed> $payload */
    private function detectAudio(array $ctx, ?array $msg, array $payload): bool
    {
        try {
            // Check pre-existing flag
            if (isset($ctx['__is_audio']) && $ctx['__is_audio']) {
                return true;
            }
            if (isset($ctx['is_audio']) && $ctx['is_audio']) {
                return true;
            }

            // Extract _data from payload
            $data = [];
            if (isset($payload['_data']) && is_array($payload['_data'])) {
                $data = $payload['_data'];
            }

            $info = [];
            if (isset($data['Info']) && is_array($data['Info'])) {
                $info = $data['Info'];
            }

            $msgObj = [];
            if (isset($data['Message']) && is_array($data['Message'])) {
                $msgObj = $data['Message'];
            }

            $audioMsg = null;
            if (isset($msgObj['audioMessage']) && is_array($msgObj['audioMessage'])) {
                $audioMsg = $msgObj['audioMessage'];
            }

            $media = null;
            if (isset($payload['media']) && is_array($payload['media'])) {
                $media = $payload['media'];
            }

            // 1) Type field check
            $type = mb_strtolower((string) (
                (is_array($msg) ? ($msg['type'] ?? '') : '')
                ?: ($payload['type'] ?? '')
                ?: ($data['type'] ?? '')
            ));

            if ($type === '') {
                $type = mb_strtolower((string) ($info['MediaType'] ?? $info['Type'] ?? ''));
            }

            $audioTypes = ['audio', 'ptt', 'voice', 'voice_note', 'voicenote',
                           'voice-message', 'voice_message'];

            if (in_array($type, $audioTypes, true)) {
                return true;
            }

            // 2) MIME type check
            $mime = mb_strtolower((string) (
                (is_array($msg) ? ($msg['mimetype'] ?? $msg['mimeType'] ?? $msg['mime_type'] ?? '') : '')
                ?: ($payload['mimetype'] ?? $payload['mimeType'] ?? $payload['mime_type'] ?? '')
                ?: ($data['mimetype'] ?? $data['mimeType'] ?? $data['mime_type'] ?? '')
                ?: (is_array($media) ? ($media['mimetype'] ?? $media['mimeType'] ?? $media['mime_type'] ?? '') : '')
                ?: (is_array($audioMsg) ? ($audioMsg['mimetype'] ?? $audioMsg['mimeType'] ?? $audioMsg['mime_type'] ?? '') : '')
            ));

            if (str_starts_with($mime, 'audio/') || str_contains($mime, 'audio/')) {
                return true;
            }

            // 3) hasMedia + ptt flag check
            $hasMedia = (
                (is_array($msg) && ($msg['hasMedia'] ?? false) === true)
                || ($payload['hasMedia'] ?? false) === true
                || ($data['hasMedia'] ?? false) === true
                || $media !== null
                || $audioMsg !== null
            );

            $isPtt = (
                (is_array($msg) && ($msg['ptt'] ?? false) === true)
                || ($payload['ptt'] ?? false) === true
                || ($data['ptt'] ?? false) === true
                || (is_array($audioMsg) && ($audioMsg['PTT'] ?? false) === true)
            );

            if ($hasMedia && $isPtt) {
                return true;
            }

            // 4) URL pattern check
            $url = mb_strtolower((string) (
                (is_array($media) ? ($media['url'] ?? $media['URL'] ?? '') : '')
                ?: (is_array($audioMsg) ? ($audioMsg['URL'] ?? $audioMsg['url'] ?? $audioMsg['directPath'] ?? '') : '')
            ));

            if ($url !== '' && (str_contains($url, '.oga') || str_contains($url, '.ogg') || str_contains($url, 'audio'))) {
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Detect whether the message is an image without text (image-only).
     *
     * Checks MediaType, mimetype, imageMessage struct, and type field.
     * Returns false if the message has text/caption (not a "pure" image).
     *
     * Port of detectImageFrom from bot.json.
     *
     * @param array<string, mixed>    $ctx
     * @param array<string, mixed>|null $msg
     * @param array<string, mixed>    $payload
     */
    /** @param array<string, mixed> $ctx @param array<string, mixed>|null $msg @param array<string, mixed> $payload */
    private function detectImage(array $ctx, ?array $msg, array $payload): bool
    {
        try {
            // Extract _data
            $data = [];
            if (isset($payload['_data']) && is_array($payload['_data'])) {
                $data = $payload['_data'];
            }

            $info = [];
            if (isset($data['Info']) && is_array($data['Info'])) {
                $info = $data['Info'];
            }

            $msgObj = [];
            if (isset($data['Message']) && is_array($data['Message'])) {
                $msgObj = $data['Message'];
            }

            $media = null;
            if (isset($payload['media']) && is_array($payload['media'])) {
                $media = $payload['media'];
            }

            // 1) MediaType check
            $mediaType = mb_strtolower((string) ($info['MediaType'] ?? $info['Type'] ?? ''));

            // 2) MIME type
            $mime = mb_strtolower((string) (
                is_array($media)
                    ? ($media['mimetype'] ?? $media['mimeType'] ?? $media['mime_type'] ?? '')
                    : ''
            ));

            // 3) imageMessage struct
            $hasImageMessage = (
                isset($msgObj['imageMessage'])
                && is_array($msgObj['imageMessage'])
            );

            // 4) Type field
            $typeTop = mb_strtolower((string) (
                (is_array($msg) ? ($msg['type'] ?? '') : '')
                ?: ($payload['type'] ?? '')
            ));

            $isImageByType = (
                $mediaType === 'image'
                || $typeTop === 'image'
                || $typeTop === 'photo'
                || $typeTop === 'sticker'
                || $hasImageMessage
            );

            $isImageByMime = str_starts_with($mime, 'image/') || str_contains($mime, 'image/');

            $isImage = $isImageByType || $isImageByMime || $hasImageMessage;

            if (!$isImage) {
                return false;
            }

            // Check for text/caption — if present, it's not image-only
            $hasText = (
                (is_array($msg) && $this->pickTextWA($msg) !== '')
                || (
                    is_array($media)
                    && (
                        (isset($media['caption']) && is_string($media['caption']) && trim($media['caption']) !== '')
                        || (isset($payload['caption']) && is_string($payload['caption']) && trim($payload['caption']) !== '')
                    )
                )
            );

            return !$hasText;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Detect whether the message is a shared location (WhatsApp location / live location).
     *
     * Checks the WAHA payload.location object, the GOWS _data.Message.locationMessage
     * struct, and the type field.
     *
     * @param array<string, mixed>    $ctx
     * @param array<string, mixed>|null $msg
     * @param array<string, mixed>    $payload
     */
    /** @param array<string, mixed> $ctx @param array<string, mixed>|null $msg @param array<string, mixed> $payload */
    private function detectLocation(array $ctx, ?array $msg, array $payload): bool
    {
        try {
            // Check pre-existing flag
            if (isset($ctx['__is_location']) && $ctx['__is_location']) {
                return true;
            }
            if (isset($ctx['is_location']) && $ctx['is_location']) {
                return true;
            }

            // Direct location object (WAHA: payload.location = {latitude, longitude, name, address})
            if (isset($payload['location']) && is_array($payload['location']) && $payload['location'] !== []) {
                return true;
            }
            if ($msg !== null && isset($msg['location']) && is_array($msg['location']) && $msg['location'] !== []) {
                return true;
            }

            // GOWS engine: _data.Message.locationMessage
            $data = [];
            if (isset($payload['_data']) && is_array($payload['_data'])) {
                $data = $payload['_data'];
            }
            $msgObj = [];
            if (isset($data['Message']) && is_array($data['Message'])) {
                $msgObj = $data['Message'];
            }
            if (isset($msgObj['locationMessage']) && is_array($msgObj['locationMessage'])) {
                return true;
            }

            // Type field
            $type = mb_strtolower((string) (
                (is_array($msg) ? ($msg['type'] ?? '') : '')
                ?: ($payload['type'] ?? '')
                ?: ($data['type'] ?? '')
            ));
            if ($type === 'location' || $type === 'live_location') {
                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helper: resolve the message object from various payload shapes
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolve the message object from context/payload.
     *
     * WAHA sends payloads in several shapes:
     *  - body.payload (for event=message)
     *  - messages[] array
     *  - entry[].changes[].value.messages[] (Facebook-style)
     *
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $body
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    /** @param array<string, mixed> $ctx @param array<string, mixed> $body @param array<string, mixed> $payload @return array<string, mixed>|null */
    private function resolveMessageObject(array $ctx, array $body, array $payload): ?array
    {
        // event=message + payload → the payload IS the message
        $event = mb_strtolower((string) ($body['event'] ?? $ctx['event'] ?? ''));

        if ($event === 'message' && $payload !== []) {
            return $payload;
        }

        // messages[] array (WhatsApp Cloud API style)
        if (isset($ctx['messages']) && is_array($ctx['messages']) && $ctx['messages'] !== []) {
            return $ctx['messages'][0];
        }

        // entry[].changes[].value.messages[] (Facebook/Instagram style)
        if (isset($ctx['entry']) && is_array($ctx['entry']) && $ctx['entry'] !== []) {
            $changes = $ctx['entry'][0]['changes'] ?? [];

            if (is_array($changes)) {
                foreach ($changes as $change) {
                    if (!is_array($change)) {
                        continue;
                    }

                    $value = $change['value'] ?? null;

                    if (is_array($value) && isset($value['messages']) && is_array($value['messages']) && $value['messages'] !== []) {
                        return $value['messages'][0];
                    }
                }
            }
        }

        // Direct event + payload (used when event='message' at root level)
        if (isset($ctx['event']) && is_string($ctx['event'])
            && mb_strtolower($ctx['event']) === 'message'
            && isset($ctx['payload']) && is_array($ctx['payload'])
        ) {
            return $ctx['payload'];
        }

        return null;
    }
}
