<?php
/**
 * EvolutionApi — Cliente autocontenido para Evolution API (v2.x).
 *
 * Encapsula todas las llamadas REST a Evolution API (evofoundation) que
 * necesita el proyecto para interactuar con WhatsApp: envío de texto/media,
 * presencia/typing, leídos, historial, gestión de instancias (QR/sesión),
 * normalización de JIDs y transcripción de audio (faster-whisper local).
 *
 * No depende de Composer: usa solo curl/exec de PHP. Por eso puede usarse
 * tanto desde el CRM principal (app/) como desde bot-casa (require_once o
 * envolviéndola), compartiendo una única implementación.
 *
 * Uso:
 *   $evo = new EvolutionApi('http://HOST:8081', $apiKey, $instanceName);
 *   $evo->sendText('654464023', 'Hola');
 *   $evo->sendMedia('654464023', 'image', $base64, 'caption');
 *
 * Los métodos devuelven ['ok'=>bool, 'http_code'=>int, 'data'=>mixed, 'error'=>?string].
 */

declare(strict_types=1);

class EvolutionApi
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $instance;
    protected int $timeout;
    protected string $countryCode;

    public const DEFAULT_HOST = 'http://100.117.92.74:8081';
    public const DEFAULT_API_KEY = '';
    public const DEFAULT_INSTANCE = 'default';
    public const DEFAULT_COUNTRY_CODE = '34';

    public const MEDIA_IMAGE = 'image';
    public const MEDIA_VIDEO = 'video';
    public const MEDIA_AUDIO = 'audio';
    public const MEDIA_DOCUMENT = 'document';
    public const MEDIA_STICKER = 'sticker';

    public function __construct(
        string $baseUrl = self::DEFAULT_HOST,
        string $apiKey = self::DEFAULT_API_KEY,
        string $instance = self::DEFAULT_INSTANCE,
        int $timeout = 30,
        string $countryCode = self::DEFAULT_COUNTRY_CODE
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->instance = $instance;
        $this->timeout = $timeout;
        $this->countryCode = $countryCode;
    }

    /**
     * Nombre de la instancia Evolution asociada a este cliente.
     */
    public function instanceName(): string
    {
        return $this->instance;
    }

    /* ───────────────────────────── HTTP BASE ───────────────────────────── */

    /**
     * Llamada HTTP a Evolution. Devuelve estructura normalizada.
     */
    public function call(string $method, string $path, ?array $json = null, int $timeoutMs = 0): array
    {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'apikey: ' . $this->apiKey;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeoutMs > 0 ? intdiv($timeoutMs, 1000) : $this->timeout,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if ($json !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return ['ok' => false, 'http_code' => $httpCode, 'data' => null, 'error' => $this->safeError($error ?: 'Empty response')];
        }

        $decoded = json_decode($raw, true);
        $data = (is_array($decoded)) ? $decoded : ['raw' => $raw];

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => $data,
            'error' => ($httpCode >= 200 && $httpCode < 300) ? null : $this->safeError($data['message'] ?? $data['error'] ?? "HTTP $httpCode"),
        ];
    }

    /** Keep diagnostics useful without leaking an unbounded/API response. */
    private function safeError(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $value = trim((string) $value);
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $value = function_exists('iconv') ? (string)iconv('UTF-8', 'UTF-8//IGNORE', $value) : '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > 500 ? mb_substr($value, 0, 497, 'UTF-8') . '...' : $value;
        }
        if (strlen($value) <= 500) return $value;
        $prefix = substr($value, 0, 497);
        while ($prefix !== '' && preg_match('//u', $prefix) !== 1) {
            $prefix = substr($prefix, 0, -1);
        }
        return $prefix . '...';
    }

    /* ─────────────────────────── INSTANCIAS/SESIÓN ─────────────────────────── */

    /**
     * Estado de conexión de la instancia (open/connecting/close).
     */
    public function connectionState(): array
    {
        return $this->call('GET', "/instance/connectionState/{$this->instance}");
    }

    /**
     * Estado resumido de conexión. Devuelve el string de estado si ok.
     */
    public function getStatus(): array
    {
        $r = $this->connectionState();
        if ($r['ok'] && isset($r['data']['instance']['state'])) {
            $r['data'] = ['state' => $r['data']['instance']['state']];
        }
        return $r;
    }

    /**
     * Genera/obtiene el QR de vinculación (base64). Devuelve base64 o null.
     */
    public function getQr(bool $asDataUri = true): array
    {
        $r = $this->call('GET', "/instance/connect/{$this->instance}");
        if ($r['ok'] && isset($r['data']['base64'])) {
            $r['data']['base64_raw'] = $r['data']['base64'];
            if (!$asDataUri && str_starts_with((string) $r['data']['base64'], 'data:')) {
                $parts = explode(',', (string) $r['data']['base64'], 2);
                $r['data']['base64_raw'] = $parts[1] ?? '';
            }
        }
        return $r;
    }

    public function restart(): array
    {
        return $this->call('POST', "/instance/restart/{$this->instance}");
    }

    public function deleteInstance(): array
    {
        return $this->call('DELETE', "/instance/delete/{$this->instance}");
    }

    public function createInstance(string $name, bool $qrcode = true): array
    {
        return $this->call('POST', '/instance/create', [
            'instanceName' => $name,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => $qrcode,
        ]);
    }

    /**
     * Configura el webhook de la instancia (eventos MESSAGES_*, SEND_MESSAGE...).
     */
    public function setWebhook(string $url, array $events = ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'SEND_MESSAGE'], bool $byEvents = false): array
    {
        return $this->call('POST', "/webhook/set/{$this->instance}", [
            'webhook' => [
                'enabled' => true,
                'url' => $url,
                'webhook_by_events' => $byEvents,
                'events' => $events,
            ],
        ]);
    }

    public function getWebhook(): array
    {
        return $this->call('GET', "/webhook/find/{$this->instance}");
    }

    public function fetchInstances(): array
    {
        return $this->call('GET', '/instance/fetchInstances');
    }

    /* ─────────────────────────── ENVÍO DE MENSAJES ─────────────────────────── */

    /**
     * Descarga y descifra la media de un mensaje recibido (getBase64FromMediaMessage).
     * Devuelve ['base64'=>..., 'mimetype'=>..., 'mediaType'=>..., 'fileName'=>...] o null.
     *
     * @param array<string,mixed> $message registro completo del mensaje (key/message/messageType)
     */
    public function getMediaBase64(array $message): ?array
    {
        $r = $this->call('POST', "/chat/getBase64FromMediaMessage/{$this->instance}", ['message' => $message], 45000);
        if (!$r['ok'] || !is_array($r['data'])) {
            return null;
        }
        $b64 = (string) ($r['data']['base64'] ?? '');
        if ($b64 === '') {
            return null;
        }
        return [
            'base64' => $b64,
            'mimetype' => (string) ($r['data']['mimetype'] ?? 'application/octet-stream'),
            'mediaType' => (string) ($r['data']['mediaType'] ?? ''),
            'fileName' => (string) ($r['data']['fileName'] ?? ''),
        ];
    }

    /**
     * Envía un mensaje de texto.
     */
    public function sendText(string $chatId, string $text, ?int $delayMs = null): array
    {
        $body = ['number' => $this->toJid($chatId), 'text' => $text];
        if ($delayMs !== null) {
            $body['delay'] = $delayMs;
        }
        return $this->call('POST', "/message/sendText/{$this->instance}", $body);
    }

    /**
     * Envía media nativa (imagen/vídeo/audio/documento/sticker).
     * $media puede ser base64 (con o sin prefijo data:) o una URL pública.
     */
    public function sendMedia(string $chatId, string $mediaType, string $media, ?string $caption = null, ?string $fileName = null, ?int $delayMs = null): array
    {
        $body = [
            'number' => $this->toJid($chatId),
            'mediatype' => $mediaType,
            'media' => $media,
        ];
        if ($caption !== null) $body['caption'] = $caption;
        if ($fileName !== null) $body['fileName'] = $fileName;
        if ($delayMs !== null) $body['delay'] = $delayMs;
        return $this->call('POST', "/message/sendMedia/{$this->instance}", $body);
    }

    /**
     * Envía un audio (ptt). $media: URL pública o base64.
     * Nota: en esta versión de Evolution el audio vía base64 puede fallar
     * (quirk); se recomienda pasar una URL pública accesible.
     */
    public function sendAudio(string $chatId, string $media, ?int $delayMs = null): array
    {
        return $this->sendMedia($chatId, self::MEDIA_AUDIO, $media, null, null, $delayMs);
    }

    /**
     * Marca mensajes como leídos. $messages = [[remoteJid, id, fromMe], ...].
     */
    public function markMessageAsRead(array $messages): array
    {
        return $this->call('POST', "/chat/markMessageAsRead/{$this->instance}", ['readMessages' => $messages]);
    }

    /**
     * Marca como leído un chat completo (busca el último mensaje y lo marca).
     */
    public function markChatAsRead(string $chatId): array
    {
        $msgs = $this->findMessages(['key.remoteJid' => $this->toJid($chatId)], 1);
        $records = $msgs['data']['messages']['records'] ?? [];
        if (empty($records)) {
            return ['ok' => true, 'http_code' => 200, 'data' => ['note' => 'no messages'], 'error' => null];
        }
        $first = $records[0];
        return $this->markMessageAsRead([[
            'remoteJid' => $first['key']['remoteJid'] ?? $this->toJid($chatId),
            'id' => $first['key']['id'] ?? '',
            'fromMe' => $first['key']['fromMe'] ?? false,
        ]]);
    }

    /**
     * Presencia/typing. $presence: composing|recording|paused.
     */
    public function sendPresence(string $chatId, string $presence = 'composing', int $delayMs = 0): array
    {
        return $this->call('POST', "/chat/sendPresence/{$this->instance}", [
            'number' => $this->toJid($chatId),
            'presence' => $presence,
            'delay' => $delayMs,
        ]);
    }

    public function startTyping(string $chatId, int $delayMs = 1500): array
    {
        return $this->sendPresence($chatId, 'composing', $delayMs);
    }

    public function stopTyping(string $chatId): array
    {
        return $this->sendPresence($chatId, 'paused', 0);
    }

    /**
     * Envío "humanizado": seen → typing → texto → pausa, con delays.
     * Replica el flujo anti-ban que ya usa WAHA.
     */
    public function sendHumanized(string $chatId, string $text, array $opts = []): array
    {
        $seenDelay = (int) ($opts['seen_delay_ms'] ?? 400);
        $typingDelay = (int) ($opts['typing_ms'] ?? 1500);
        $afterDelay = (int) ($opts['after_ms'] ?? 600);

        $chatJid = $this->toJid($chatId);
        $this->markChatAsRead($chatJid);
        if ($seenDelay > 0) usleep($seenDelay * 1000);
        $this->startTyping($chatJid, $typingDelay);

        $send = $this->sendText($chatJid, $text);
        $this->stopTyping($chatJid);
        if ($afterDelay > 0) usleep($afterDelay * 1000);

        return $send;
    }

    /* ─────────────────────────── HISTORIAL / RECEPCIÓN ─────────────────────────── */

    /**
     * Consulta mensajes persistidos en la BD de Evolution.
     */
    public function findMessages(array $where = [], int $take = 50, int $skip = 0, array $orderBy = []): array
    {
        $body = ['take' => $take, 'skip' => $skip];
        if (!empty($where)) $body['where'] = $where;
        if (!empty($orderBy)) $body['orderBy'] = $orderBy;
        return $this->call('POST', "/chat/findMessages/{$this->instance}", $body);
    }

    /**
     * Historial de un chat (mensajes más recientes primero).
     */
    public function getChatHistory(string $chatId, int $limit = 50): array
    {
        return $this->findMessages(
            ['key.remoteJid' => $this->toJid($chatId)],
            $limit,
            0,
            ['messageTimestamp' => 'desc']
        );
    }

    /**
     * Lista de chats.
     */
    public function findChats(int $take = 50, int $skip = 0): array
    {
        return $this->call('POST', "/chat/findChats/{$this->instance}", ['take' => $take, 'skip' => $skip]);
    }

    /* ─────────────────────────── NORMALIZACIÓN DE JID ─────────────────────────── */

    /**
     * Convierte un teléfono (con o sin código de país) a JID de Evolution (@s.whatsapp.net).
     */
    public function toJid(string $phone): string
    {
        if (str_contains($phone, '@')) {
            return $phone;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return $phone;
        }
        // Si ya incluye el código de país, no duplicar.
        if (!str_starts_with($digits, $this->countryCode)) {
            $digits = $this->countryCode . $digits;
        }
        return $digits . '@s.whatsapp.net';
    }

    public static function extractPhoneFromJid(string $jid): string
    {
        $parts = explode('@', $jid, 2);
        return preg_replace('/\D+/', '', $parts[0] ?? '');
    }

    /**
     * Normaliza un JID de cualquier backend a solo dígitos.
     */
    public static function normalizeJidToDigits(string $jid): string
    {
        return preg_replace('/[^0-9]/', '', explode('@', $jid)[0]);
    }

    /* ─────────────────────────── MEDIA / TRANSCRIPCIÓN ─────────────────────────── */

    /**
     * Extrae la URL de media de un mensaje Evolution (imageMessage/audioMessage/...).
     */
    public static function mediaUrlFromMessage(array $message): ?array
    {
        $types = ['imageMessage' => 'image', 'audioMessage' => 'audio', 'videoMessage' => 'video', 'documentMessage' => 'document', 'stickerMessage' => 'sticker'];
        foreach ($types as $key => $kind) {
            if (isset($message[$key]) && is_array($message[$key])) {
                $m = $message[$key];
                return [
                    'type' => $kind,
                    'url' => $m['url'] ?? null,
                    'mimetype' => $m['mimetype'] ?? null,
                    'caption' => $m['caption'] ?? $m['fileName'] ?? null,
                    'fileName' => $m['fileName'] ?? null,
                    'mediaKey' => isset($m['mediaKey']) ? json_encode($m['mediaKey']) : null,
                ];
            }
        }
        return null;
    }

    /**
     * Transcribe un archivo de audio con faster-whisper (local).
     * Convierte a wav 16k mono con ffmpeg y llama al helper python.
     */
    public function transcribeAudio(string $audioFile, string $model = 'small'): ?string
    {
        if (!is_file($audioFile)) {
            return null;
        }
        $helper = __DIR__ . '/whisper_helper.py';
        if (!is_file($helper)) {
            return null;
        }
        $wav = tempnam(sys_get_temp_dir(), 'evo_wav_') . '.wav';
        @exec(sprintf('ffmpeg -y -i %s -ar 16000 -ac 1 %s 2>/dev/null', escapeshellarg($audioFile), escapeshellarg($wav)), $o, $rc);
        if ($rc !== 0 || !is_file($wav)) {
            @unlink($wav);
            return null;
        }
        $cmd = sprintf('python3 %s %s %s 2>/dev/null', escapeshellarg($helper), escapeshellarg($wav), escapeshellarg($model));
        @exec($cmd, $out, $rc);
        @unlink($wav);
        if ($rc !== 0 || empty($out)) {
            return null;
        }
        $text = trim(implode("\n", $out));
        return $text !== '' ? $text : null;
    }
}
