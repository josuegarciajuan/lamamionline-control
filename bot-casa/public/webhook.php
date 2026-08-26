<?php

declare(strict_types=1);

/**
 * webhook.php — WAHA webhook endpoint.
 *
 * Receives incoming WhatsApp message payloads from the WAHA HTTP API,
 * passes them through the Bot pipeline, and returns HTTP 200 with JSON.
 *
 * Request:  POST {JSON body from WAHA webhook}
 * Response: 200 {"status":"ok"} or 500 {"status":"error","message":"..."}
 */

// Allow enough time for humanized delays (typing simulation) + OpenAI calls
set_time_limit(300);

// ─────────────────────────────────────────────────────────────────────
//  Autoload
// ─────────────────────────────────────────────────────────────────────

define('WASAPBOT_ROOT', dirname(__DIR__));

$vendorAutoload = WASAPBOT_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    // Manual PSR-4 autoloader: WasapBot\ → src/
    spl_autoload_register(function (string $class): void {
        $prefix = 'WasapBot\\';
        $prefixLen = strlen($prefix);

        if (strncmp($prefix, $class, $prefixLen) !== 0) {
            return;
        }

        $relativeClass = substr($class, $prefixLen);
        $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// Helper de transcripción de audio (faster-whisper) para media de Evolution
$_transcribeHelper = dirname(__DIR__, 2) . '/app/evolution/transcribe.php';
if (file_exists($_transcribeHelper)) {
    require_once $_transcribeHelper;
}

/**
 * Extrae info de media (type/url) de un payload de WAHA o Evolution.
 * @param array<string,mixed> $payload
 * @param array<string,mixed> $body
 * @return array<string,mixed>|null ['type'=>..., 'url'=>..., 'mimetype'=>...]
 */
function wasap_extract_media_info(array $payload, array $body): ?array
{
    // Evolution: message.audioMessage / imageMessage / videoMessage
    $message = $payload['message'] ?? $body['message'] ?? null;
    if (is_array($message)) {
        foreach (['audioMessage' => 'audio', 'imageMessage' => 'image', 'videoMessage' => 'video', 'documentMessage' => 'document'] as $k => $type) {
            if (isset($message[$k]) && is_array($message[$k])) {
                $m = $message[$k];
                return ['type' => $type, 'url' => $m['url'] ?? null, 'mimetype' => $m['mimetype'] ?? null, 'fileName' => $m['fileName'] ?? null];
            }
        }
    }
    // WAHA: body.media / payload.media
    $media = $body['media'] ?? $payload['media'] ?? null;
    if (is_array($media)) {
        $url = $media['url'] ?? $media['URL'] ?? $media['link'] ?? null;
        $type = strtolower((string)($media['mimetype'] ?? ''));
        $kind = str_contains($type, 'audio') ? 'audio' : (str_contains($type, 'image') ? 'image' : 'media');
        return ['type' => $kind, 'url' => is_string($url) ? $url : null, 'mimetype' => $type, 'fileName' => null];
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────
//  Verify request method
// ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Method not allowed. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────────────
//  Main handler
// ─────────────────────────────────────────────────────────────────────

function webhookPausedThreadsPath(int $userId): string
{
    return $userId > 1
        ? \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'paused_threads.ndjson')
        : WASAPBOT_ROOT . '/data/paused_threads.ndjson';
}

try {
    // ── Resolve user_id from the incoming payload (last9 → user_id) ──
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || $rawBody === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Empty request body'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Payload must be a JSON object'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Extract last9 from payload to determine user_id ──────────────
    $userId = 1; // default: admin / legacy
    $last9  = '';
    $body   = $payload['payload'] ?? $payload;
    // me is at the outer level in WAHA webhooks, not nested inside payload
    $me     = $payload['me'] ?? $body['me'] ?? null;
    $receiverId = '';
    if (is_array($me) && isset($me['id'])) {
        $receiverId = (string) $me['id'];
    } elseif (isset($body['to'])) {
        $receiverId = (string) $body['to'];
    }
    if ($receiverId !== '') {
        $digits = preg_replace('/[^0-9]/', '', $receiverId) ?? '';
        $last9  = $digits !== '' ? mb_substr($digits, -9) : '';
        if ($last9 !== '') {
            $linesMapPath = WASAPBOT_ROOT . '/data/lines_map.json';
            if (file_exists($linesMapPath)) {
                $linesMap = @json_decode((string) @file_get_contents($linesMapPath), true);
                if (is_array($linesMap) && isset($linesMap[$last9])) {
                    $userId = (int) $linesMap[$last9];
                } else {
                    // Line not mapped → the message will be attributed to admin
                    // and written to root data files. The owning client won't see
                    // it in their chat. Log a strong warning so this can be
                    // diagnosed and the lines_map.json can be updated.
                    if ($last9 !== '') {
                        error_log('[wasapBot] WARNING: last9 ' . $last9 . ' not found in lines_map.json — message will be attributed to admin (user 1)');
                    }
                }
            }
        }
    }

    // Bootstrap with user-specific data
    $instances = \WasapBot\Bot::bootstrap(WASAPBOT_ROOT, $userId);
    $bot       = $instances['bot'];
    $logger    = $instances['logger'];

    // ── Extract sender phone and message text ──
    $senderPhone = '';
    $rawFrom = $body['from'] ?? $payload['from'] ?? '';
    // GOWS engine: real phone is in _data.Info.SenderAlt (from is a LID like 277476546711679@lid)
    $dataInfo = $body['_data']['Info'] ?? $payload['_data']['Info'] ?? [];
    if (is_array($dataInfo) && !empty($dataInfo['SenderAlt'])) {
        $senderPhone = preg_replace('/[^0-9]/', '', (string) $dataInfo['SenderAlt']);
    }
    // Fall back to the from field (stripped of non-digits)
    if ($senderPhone === '') {
        $senderPhone = preg_replace('/[^0-9]/', '', (string) $rawFrom);
    }

    // ── Detect fromMe / source / peer phone (native WhatsApp vs bot API) ──
    $resolved = \WasapBot\Pipeline\FromMeResolver::resolve(
        is_array($body) ? $body : [],
        $payload,
        is_array($dataInfo) ? $dataInfo : [],
        (string) $senderPhone,
    );
    $fromMe      = $resolved['from_me'];
    $source      = $resolved['source'];
    $senderPhone = $resolved['sender_phone'];

    // ── Skip our own API-sent messages (source=api) ──
    // The bot's own replies (sent via WAHA API) are already written to
    // session_memory by the pipeline. Treating them as native replies here
    // would double-write them and wrongly auto-pause the thread.
    if ($source === 'api') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'ok',
            'message' => 'API-sent message skipped (already persisted by pipeline)',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $msgText = '';
    $bodyText = $body['body'] ?? $payload['body'] ?? null;
    if (is_string($bodyText)) {
        $msgText = trim($bodyText);
    } elseif (is_array($bodyText)) {
        $msgText = trim((string) ($bodyText['text']['body'] ?? $bodyText['text'] ?? $bodyText['message'] ?? ''));
    }

    // Detect non-text message type for persistence when bot is stopped.
    // Text messages are handled normally; non-text (audio, image, sticker,
    // location, document, video, vCard) get a descriptive placeholder
    // user_msg so they still appear in the chat UI when the bot is off.
    $nonTextType = '';
    if ($senderPhone !== '' && $msgText === '') {
        $dataInfoFull = $body['_data'] ?? $payload['_data'] ?? [];
        if (is_array($dataInfoFull)) {
            $nonTextType = mb_strtolower(trim((string) ($dataInfoFull['MessageType'] ?? '')));
        }
        if ($nonTextType === '') {
            if (!empty($body['media']) || !empty($payload['media']))         { $nonTextType = 'media'; }
            elseif (!empty($body['location']) || !empty($payload['location'])) { $nonTextType = 'location'; }
            elseif (!empty($body['vcard']) || !empty($payload['vcard']))       { $nonTextType = 'vcard'; }
            elseif (!empty($body['document']) || !empty($payload['document'])) { $nonTextType = 'document'; }
        }
        // Generic fallback: if the event is 'message' but we have no
        // extractable text body, it's some unrecognised media type.
        if ($nonTextType === '' && ($payload['event'] ?? '') === 'message') {
            $nonTextType = 'media';
        }
    }

    // user_msg to show in chat: real text, or descriptive placeholder
    $displayText = $msgText;
    if ($displayText === '') {
        switch ($nonTextType) {
            case 'audio':    $displayText = '🎤 Audio'; break;
            case 'image':    $displayText = '🖼️ Imagen'; break;
            case 'sticker':  $displayText = '🌟 Sticker'; break;
            case 'location': $displayText = '📍 Ubicación'; break;
            case 'document': $displayText = '📄 Documento'; break;
            case 'video':    $displayText = '🎬 Vídeo'; break;
            case 'vcard':
            case 'contact':  $displayText = '👤 Contacto'; break;
            default:         $displayText = '📎 Mensaje'; break;
        }
    }

    // ── Transcripción de audio (media descargable, p.ej. Evolution/MinIO) ──
    $transcription = '';
    $mediaInfo = null;
    if (($nonTextType === 'audio' || $msgText === '') && function_exists('whatsapp_transcribe_media')) {
        $mediaInfo = wasap_extract_media_info($payload, $body);
        if ($mediaInfo !== null && ($mediaInfo['type'] ?? '') === 'audio') {
            $trans = whatsapp_transcribe_media($mediaInfo);
            if ($trans !== null && trim($trans) !== '') {
                $transcription = trim($trans);
                $displayText = $transcription;
            }
        }
    }

    // Deduplication content: real text if available, otherwise synthetic key.
    $dedupContent = $msgText !== '' ? $msgText : ($nonTextType !== '' ? ('__' . $nonTextType) : '');

    // _pending write is deferred until after isRunning() and thread-pause checks.
    $threadId        = '';
    $senderLid       = '';
    $isWritePending  = false;
    $threadPaused    = false;

    if ($senderPhone !== '' && $dedupContent !== '') {
        // Compute threadId early (needed for dedup key AND record writing)
        $threadId = ($last9 !== '' ? $last9 : '000000000') . '_' . $senderPhone;

        // Deduplicate webhook deliveries: WAHA may retry if the pipeline
        // takes too long (humanized delays + OpenAI). Skip if the same
        // message was already written within the last 15 seconds.
        $dedupKey = $threadId . '|' . md5($senderPhone . '|' . $dedupContent);
        $dedupFile = WASAPBOT_ROOT . '/data/.webhook_dedup.json';
        $dedup = [];
        if (file_exists($dedupFile)) {
            $dedup = json_decode((string) @file_get_contents($dedupFile), true) ?: [];
        }
        $now = time();
        // Prune entries older than 15s
        foreach ($dedup as $key => $ts) {
            if (!is_int($ts) || ($now - $ts) > 15) unset($dedup[$key]);
        }
        $isDuplicate = isset($dedup[$dedupKey]);
        if (!$isDuplicate) {
            $dedup[$dedupKey] = $now;
            if (count($dedup) > 500) {
                $dedup = array_slice($dedup, -300, 300, true);  // keep last 300 entries
            }
            $dedupDir = dirname($dedupFile);
            if (!is_dir($dedupDir)) @mkdir($dedupDir, 0755, true);
            @file_put_contents($dedupFile, json_encode($dedup), LOCK_EX);

            // Detect GOWS LID for sendSeen via chat UI
            if (is_string($rawFrom) && stripos($rawFrom, '@lid') !== false) {
                $senderLid = $rawFrom;
            }
            // Flag: write _pending later (after isRunning + thread-pause checks)
            $isWritePending = true;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Optional: shared-secret webhook authentication
    //  Set waha.webhook_secret in config.local.json to enable.
    //  Empty = no auth (trusted network / localhost).
    // ─────────────────────────────────────────────────────────────────

    $webhookSecret = (string) $instances['config']->get('waha.webhook_secret', '');
    if ($webhookSecret !== '') {
        $providedSecret = $_SERVER['HTTP_X_WAHA_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        if (!hash_equals($webhookSecret, $providedSecret)) {
            $logger->warning('webhook.php — invalid webhook secret');
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Raw body already read and parsed above for user routing

    // Save raw payload for debugging
    $rawPayloadPath = (string) $instances['config']->get('files.wa_raw_payload', '');
    if ($rawPayloadPath !== '') {
        // Resolve relative path against project root
        if (!str_starts_with($rawPayloadPath, '/')) {
            $rawPayloadPath = WASAPBOT_ROOT . '/' . ltrim($rawPayloadPath, '/');
        }
        $rawPayloadDir = dirname($rawPayloadPath);
        if (!is_dir($rawPayloadDir)) {
            @mkdir($rawPayloadDir, 0755, true);
        }
        @file_put_contents(
            $rawPayloadPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
            LOCK_EX,
        );
    }

    // Check if bot is running (determine before the write so we know whether to
    // set _pending to false — stopped bot won't reply, no typing indicator needed)
    $botIsRunning = $bot->isRunning();

    // ── Write incoming message to session_memory ──
    // Always persist the incoming message so it appears in the chat UI,
    // even when the bot is stopped or the thread is paused.
    //   bot running + thread not paused → _pending=true  (typing indicator, bot will reply)
    //   bot stopped  or  thread paused   → _pending=false (visible, no typing indicator)
    if ($isWritePending) {
        // Check if this specific thread is paused
        $threadPaused = false;
        $pausedFile = webhookPausedThreadsPath($userId);
        if ($threadId !== '' && file_exists($pausedFile)) {
            $pausedLines = @file($pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($pausedLines) {
                foreach ($pausedLines as $pLine) {
                    $pRec = json_decode($pLine, true);
                    if (is_array($pRec) && ((string) ($pRec['thread_id'] ?? '')) === $threadId) {
                        $threadPaused = true;
                        break;
                    }
                }
            }
        }

        $immediateRecord = [
            '_seq'       => 0,
            'ts'         => gmdate('Y-m-d\TH:i:s\Z'),
            'thread_id'  => $threadId,
            'phone'      => $senderPhone,
            'user_msg'   => $fromMe ? '' : $displayText,
            'bot_reply'  => $fromMe ? $displayText : '',
            'sender_lid' => $senderLid,
            '_pending'   => $fromMe ? false : ($botIsRunning && !$threadPaused),
            'from_me'    => $fromMe,
        ];
        if ($transcription !== '') {
            $immediateRecord['transcription'] = $transcription;
        }
        if (is_array($mediaInfo)) {
            $immediateRecord['media'] = $mediaInfo;
        }
        try {
            $memPath = (string) $instances['config']->get('files.session_memory', '');
            if ($memPath !== '') {
                if (!str_starts_with($memPath, '/')) {
                    $memPath = WASAPBOT_ROOT . '/' . ltrim($memPath, '/');
                }
                $memDir = dirname($memPath);
                if (!is_dir($memDir)) @mkdir($memDir, 0755, true);

                // Self-heal: if the file exists but is owned by another user
                // (e.g. root), www-data can't FILE_APPEND. Try chmod in-place
                // — NEVER unlink/destroy conversation history.
                if (file_exists($memPath) && !is_writable($memPath)) {
                    if (@chmod($memPath, 0664)) {
                        clearstatcache(true, $memPath);
                        $logger->info('webhook.php — self-heal: fixed permissions on session_memory file', [
                            'path' => $memPath,
                        ]);
                    } else {
                        $logger->warning('webhook.php — self-heal: session_memory not writable and chmod failed, skipping immediate write (history preserved)', [
                            'path' => $memPath,
                        ]);
                        // Cannot write — skip the immediate FILE_APPEND below.
                        $memPath = '';
                    }
                }

                $written = false;
                if ($memPath !== '') {
                    $written = @file_put_contents(
                        $memPath,
                        json_encode($immediateRecord, JSON_UNESCAPED_UNICODE) . "\n",
                        FILE_APPEND | LOCK_EX,
                    );
                }
                if ($written === false) {
                    $logger->error('webhook.php — failed to write immediate record to session_memory', [
                        'path'          => $memPath,
                        'thread_id'     => $threadId,
                        'dir_exists'    => is_dir($memDir) ? 'yes' : 'no',
                        'dir_writable'  => is_writable($memDir) ? 'yes' : 'no',
                        'file_exists'   => file_exists($memPath) ? 'yes' : 'no',
                        'file_writable' => file_exists($memPath) ? (is_writable($memPath) ? 'yes' : 'no') : 'new_file',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $logger->error('webhook.php — exception writing immediate record: ' . $e->getMessage(), [
                'thread_id' => $threadId ?? '',
            ]);
        }
    }

    // ── fromMe bypass: don't run pipeline for messages we sent ourselves ──
    if ($fromMe && $senderPhone !== '') {
        // Auto-pause: human replied from native WA → stop bot for this conversation
        if (!$threadPaused && $threadId !== '') {
            $pausedFile = webhookPausedThreadsPath($userId);
            $pausedDir = dirname($pausedFile);
            if (!is_dir($pausedDir)) @mkdir($pausedDir, 0700, true);
            $rec = json_encode(['thread_id' => $threadId, 'paused_at' => gmdate('c')], JSON_UNESCAPED_UNICODE);
            @file_put_contents($pausedFile, $rec . "\n", FILE_APPEND | LOCK_EX);
            $logger->info('webhook.php — auto-paused thread after native WA reply', ['thread_id' => $threadId]);
        }
        $logger->info('webhook.php — fromMe message persisted, skipping pipeline');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'ok',
            'message' => 'fromMe message saved',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // If this specific thread is paused, exit after persisting — don't run pipeline
    if ($threadPaused) {
        $logger->info('webhook.php — thread paused, message persisted, skipping pipeline');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'ok',
            'message' => 'Thread is paused — message saved',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // If bot is stopped, exit after persisting the message (don't run pipeline)
    if (!$botIsRunning) {
        $logger->info('webhook.php — bot is stopped, message persisted, skipping pipeline');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'ok',
            'message' => 'Bot is stopped — message saved',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Subscription enforcement ─────────────────────────────────────
    // Expired trial/subscription → the bot stops replying for this user.
    // The incoming message is already persisted above (visible in the chat
    // UI), but the pipeline (LLM reply, states, leads) is skipped.
    if ($userId > 1) {
        try {
            $subCheck = new \WasapBot\Core\SubscriptionManager(
                new \WasapBot\Core\UserManager(WASAPBOT_ROOT)
            );
            if ($subCheck->isExpired($userId)) {
                $logger->info('webhook.php — subscription expired, skipping pipeline', [
                    'user_id'   => $userId,
                    'thread_id' => $threadId,
                ]);
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status'  => 'ok',
                    'message' => 'Subscription expired — message saved',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (\Throwable $e) {
            $logger->error('webhook.php — subscription check failed: ' . $e->getMessage(), [
                'user_id' => $userId,
            ]);
            // Fall through: never block a reply because of a broken check.
        }
    }

    // Process through pipeline
    if ($transcription !== '') {
        $payload['transcription'] = $transcription;
    }
    if (is_array($mediaInfo)) {
        $payload['media'] = $mediaInfo;
    }
    $result = $bot->handleWebhook($payload);

    // Respond OK regardless of result (WAHA needs 200 to mark as delivered)
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'   => 'ok',
        'accepted' => $result !== null,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // Catch-all: never expose stack traces to the client
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    if (isset($logger)) {
        $logger->error('webhook.php — unhandled exception: ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    } else {
        error_log('[wasapBot] webhook exception: ' . $e->getMessage());
    }

    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error',
    ], JSON_UNESCAPED_UNICODE);
}
