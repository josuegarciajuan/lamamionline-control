<?php
/**
 * comercial_humanize.php — Delays humanos para el inbox comercial.
 *
 * Adaptado de bot-casa/src/Services/WahaApi.php (sendHumanized).
 * Ajustado para contexto comercial: más rápido, sin habituation,
 * sin pace matching, sin burst detection.
 *
 * CPS: 5-10 (más rápido que bot-casa: 3-8)
 * Corrección: 8% (bot-casa: 12%)
 * Sin habituation (ritmo constante, no acelera)
 * Sin pace matching (no copia velocidad del usuario)
 * Presend sleep: 3s (bot-casa: 6s)
 */

declare(strict_types=1);

/**
 * Aplica las reglas no negociables de tono antes de enviar un mensaje comercial.
 * Es un último control determinista: protege también plantillas y respuestas que
 * no hayan pasado por el generador o el crítico LLM.
 */
function comercial_humanize_outbound_message(string $text): string {
    $text = str_replace(array('¿', '¡'), '', $text);
    $text = preg_replace(
        '/\s*(?:quieres\s+que\s+te\s+explique\s+(?:algo\s+)?m[áa]s|te\s+(?:ayudo|cuento|explico)\s+(?:algo|en\s+algo)\s+m[áa]s)\?*\s*$/iu',
        '',
        $text
    );

    return trim((string)$text);
}

/**
 * Envía un mensaje con simulación de escritura humana.
 *
 * @param string $wahaHost  URL base de WAHA (ej: http://100.117.92.74:3000)
 * @param string $session   Nombre de sesión WAHA (default)
 * @param string $chatId    ID del chat (ej: 34123456789@c.us)
 * @param string $text      Texto del mensaje a enviar
 * @return array            ['ok' => bool, 'error' => string, ...]
 */
function comercial_humanize_send(string $wahaHost, string $session, string $chatId, string $text): array {
    if ($text === '') {
        return array('ok' => false, 'error' => 'empty_text');
    }

    $wahaHost = rtrim($wahaHost, '/');
    $session = trim($session) !== '' ? trim($session) : 'default';

    // ── 1. Simular lectura (seen) ──
    comercial_humanize_seen($wahaHost, $session, $chatId);

    // ── 2. Delay de lectura ──
    comercial_humanize_read_delay($text);

    // ── 3. Iniciar typing indicator ──
    comercial_humanize_start_typing($wahaHost, $session, $chatId);

    // ── 4. Simular escritura ──
    comercial_humanize_type_delay($text);

    // ── 5. Posible corrección (8% prob) ──
    $corrected = comercial_humanize_maybe_correct($wahaHost, $session, $chatId);

    // ── 6. Enviar texto ──
    $sendResult = comercial_humanize_send_text($wahaHost, $session, $chatId, $text);

    // ── 7. Parar typing ──
    comercial_humanize_stop_typing($wahaHost, $session, $chatId);

    // ── 8. Delay post-envío ──
    comercial_humanize_after_send_delay();

    return $sendResult;
}

/**
 * Envía múltiples mensajes con delays entre ellos (para mensajes partidos).
 */
function comercial_humanize_send_multiple(string $wahaHost, string $session, string $chatId, array $messages): array {
    $results = array();
    $presendSleep = 3000000; // 3 segundos entre mensajes (microsegundos)

    foreach ($messages as $i => $text) {
        if ($i > 0) {
            usleep($presendSleep);
        }
        $results[] = comercial_humanize_send($wahaHost, $session, $chatId, $text);
    }

    return $results;
}

// ═══════════════════════════════════════════════════════════════
//  DELAYS
// ═══════════════════════════════════════════════════════════════

function comercial_humanize_seen(string $wahaHost, string $session, string $chatId): void {
    // 0.5 - 1.5 segundos (más rápido que bot-casa: 1-3s)
    $delayUs = random_int(500000, 1500000);
    usleep($delayUs);

    // Marcar como visto en WAHA (fire and forget, no bloqueante)
    $url = $wahaHost . '/api/sendSeen';
    $body = json_encode(array(
        'session' => $session,
        'chatId' => $chatId,
    ));
    comercial_humanize_fire_and_forget($url, $body);
}

function comercial_humanize_read_delay(string $text): void {
    $chars = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    $perCharMs = 15000; // 15ms por carácter (bot-casa: 22ms)
    $baseMs = random_int(300, 800); // (bot-casa: 500-1200ms)

    // Mensajes cortos (< 15 chars) → delay reducido
    if ($chars < 15) {
        $baseMs = random_int(200, 500);
    }

    $totalMs = $baseMs + ($chars * $perCharMs);
    $totalMs = max(500, min(8000, $totalMs)); // Clamp: 0.5-8s (bot-casa: 1.1-12s)

    usleep($totalMs * 1000);
}

function comercial_humanize_start_typing(string $wahaHost, string $session, string $chatId): void {
    // Delay antes de empezar a "escribir": 500-1200ms (bot-casa: 800-2000ms)
    $delayUs = random_int(500000, 1200000);
    usleep($delayUs);

    $url = $wahaHost . '/api/startTyping';
    $body = json_encode(array(
        'session' => $session,
        'chatId' => $chatId,
    ));
    comercial_humanize_fire_and_forget($url, $body);
}

function comercial_humanize_type_delay(string $text): void {
    $chars = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    $cps = random_int(5, 10); // Caracteres por segundo (bot-casa: 3-8)
    $startMs = random_int(300, 800); // Delay inicial

    $totalMs = $startMs + (int)(($chars / $cps) * 1000);

    // Añadir pausas por "chunks" (cada ~30 caracteres)
    $chunkSize = 30;
    $chunks = (int)($chars / $chunkSize);
    $totalMs += $chunks * random_int(100, 400);

    // Emoji-only → máximo 600ms
    $emojiOnly = preg_match('/^[\p{Emoji}\s]+$/u', $text);
    if ($emojiOnly) {
        $totalMs = min($totalMs, 600);
    }

    $totalMs = max(800, min(8000, $totalMs)); // Clamp: 0.8-8s

    usleep($totalMs * 1000);
}

function comercial_humanize_maybe_correct(string $wahaHost, string $session, string $chatId): bool {
    // 8% de probabilidad de simular corrección (bot-casa: 12%)
    if (random_int(1, 100) > 8) {
        return false;
    }

    // Parar typing → pausa → volver a empezar typing
    comercial_humanize_stop_typing($wahaHost, $session, $chatId);
    usleep(random_int(300000, 1200000)); // 300-1200ms (bot-casa: 400-1800ms)
    comercial_humanize_start_typing($wahaHost, $session, $chatId);

    // Pequeña pausa extra tras "reescribir"
    usleep(random_int(200000, 600000));

    return true;
}

function comercial_humanize_stop_typing(string $wahaHost, string $session, string $chatId): void {
    $url = $wahaHost . '/api/stopTyping';
    $body = json_encode(array(
        'session' => $session,
        'chatId' => $chatId,
    ));
    comercial_humanize_fire_and_forget($url, $body);
}

function comercial_humanize_after_send_delay(): void {
    // 200-800ms post-envío (bot-casa: más variable con habituation)
    usleep(random_int(200000, 800000));
}

function comercial_humanize_send_text(string $wahaHost, string $session, string $chatId, string $text): array {
    $text = comercial_humanize_outbound_message($text);
    $url = $wahaHost . '/api/sendText';
    $body = json_encode(array(
        'session' => $session,
        'chatId' => $chatId,
        'text' => $text,
    ));

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        return array('ok' => false, 'error' => 'curl_error: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        return array('ok' => false, 'error' => 'http_' . $httpCode, 'response' => $decoded);
    }

    return array('ok' => true, 'response' => $decoded);
}

// ═══════════════════════════════════════════════════════════════
//  UTIL
// ═══════════════════════════════════════════════════════════════

/**
 * Envía una petición HTTP sin esperar respuesta (fire and forget).
 */
function comercial_humanize_fire_and_forget(string $url, string $body): void {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_TIMEOUT => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
    ));
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Envía un mensaje comercial con humanización, usando los datos del thread.
 * Wrapper conveniente para integrar con comercial_send_thread_message().
 */
function comercial_humanize_send_thread_message(array $thread, string $text, string $wahaHost = ''): array {
    $thread = comercial_normalize_thread($thread);

    // Resolver host de WAHA
    if ($wahaHost === '') {
        $wahaHost = comercial_normalize_waha_host('');
    }

    // Resolver session (nombre de sesión WAHA)
    $lineId = (string)($thread['line_id'] ?? '');
    $line = $lineId !== '' ? comercial_get_line($lineId) : null;
    $session = is_array($line) ? trim((string)($line['session'] ?? 'default')) : 'default';

    // Formatear chatId: 34123456789@c.us
    $targetPhone = comercial_only_digits((string)($thread['target_phone'] ?? ''));
    $chatId = $targetPhone . '@c.us';

    return comercial_humanize_send($wahaHost, $session, $chatId, $text);
}
