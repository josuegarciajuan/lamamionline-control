<?php
/**
 * transcribe.php — Transcripción de audio recibido (faster-whisper local).
 *
 * Para líneas en Evolution la media llega descifrada y guardada en MinIO
 * (host minio:9000). Estas funciones descargan el audio, lo convierten a wav
 * y lo transcriben con faster-whisper vía whisper_helper.py.
 *
 * Para líneas en WAHA la media llega cifrada (.enc) y no se transcribe:
 * whatsapp_media_fetch_bytes() devuelve null para hosts que no sean nuestro
 * MinIO, por lo que nunca se llama a whisper en ese caso (y en tests).
 */

declare(strict_types=1);

require_once __DIR__ . '/EvolutionApi.php';

if (!function_exists('whatsapp_media_fetch_bytes')) {
    /**
     * Descarga los bytes de una media de Evolution (MinIO). Rechaza hosts ajenos.
     *
     * @param array<string,mixed> $media
     * @return string|null bytes del archivo
     */
    function whatsapp_media_fetch_bytes(array $media): ?string
    {
        $url = trim((string) ($media['url'] ?? ''));
        if ($url === '') {
            return null;
        }
        $parsed = parse_url($url);
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $port = (string) ($parsed['port'] ?? '');
        $hostPort = $port !== '' ? $host . ':' . $port : $host;
        $allowHosts = ['minio:9000', '127.0.0.1:9000', '100.117.92.74:9000', 'localhost:9000'];
        if (!in_array($hostPort, $allowHosts, true) || !isset($parsed['path'])) {
            return null;
        }
        $fetchUrl = 'http://100.117.92.74:9000' . $parsed['path'];
        if (!empty($parsed['query'])) {
            $fetchUrl .= '?' . $parsed['query'];
        }

        $ch = curl_init($fetchUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_MAXREDIRS => 3,
        ]);
        $bytes = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($bytes !== false && $http === 200) ? $bytes : null;
    }
}

if (!function_exists('whatsapp_transcribe_media')) {
    /**
     * Transcribe un audio de Evolution (tipo 'audio') con faster-whisper.
     * Devuelve null si no es audio, no se puede descargar o falla la transcripción.
     *
     * @param array<string,mixed> $media ['type'=>'audio','url'=>MinIO,...]
     */
    function whatsapp_transcribe_media(array $media, string $model = 'small'): ?string
    {
        if (($media['type'] ?? '') !== 'audio') {
            return null;
        }
        $bytes = whatsapp_media_fetch_bytes($media);
        if ($bytes === null) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'evo_audio_');
        if ($tmp === false) {
            return null;
        }
        @file_put_contents($tmp, $bytes);
        $evo = new EvolutionApi();
        $text = $evo->transcribeAudio($tmp, $model);
        @unlink($tmp);
        if ($text === null || trim($text) === '') {
            return null;
        }
        return trim($text);
    }
}
