<?php

/**
 * YouTube helper: búsqueda, detalles de vídeo, vídeos de canal temático.
 * Sin API key — scraping de la página de resultados de YouTube.
 */

function youtube_search($query, $maxAgeHours = null) {
    $query = trim((string)$query);
    if ($query === '') return array();

    $url = 'https://www.youtube.com/results?search_query=' . urlencode($query) . '&sp=CAI%3D';
    $html = _youtube_fetch($url);
    if ($html === '') return array();

    $data = _youtube_extract_initial_data($html);
    if (!$data) return array();

    $results = _youtube_parse_search_results($data);

    // Filtrar por antigüedad si se pide (con fallback: si filtra todo, devuelve sin filtrar)
    if ($maxAgeHours !== null && $maxAgeHours > 0) {
        $filtered = array();
        foreach ($results as $r) {
            $age = _youtube_parse_age_hours($r['published_time'] ?? '');
            if ($age <= $maxAgeHours) {
                $filtered[] = $r;
            }
        }
        if (!empty($filtered)) {
            return $filtered;
        }
        // Si el filtro lo ha quitado todo, devolvemos los resultados originales
    }

    return $results;
}

function youtube_video_details($videoId) {
    $videoId = trim((string)$videoId);
    if ($videoId === '') return null;

    $url = 'https://www.youtube.com/watch?v=' . urlencode($videoId);
    $html = _youtube_fetch($url);
    if ($html === '') return null;

    $data = _youtube_extract_initial_data($html);
    if (!$data) return null;

    return _youtube_parse_video_details($data, $videoId);
}

/**
 * Busca vídeos para un canal temático (busca con una query optimizada).
 */
function youtube_topic_channel_videos($query, $maxResults = 10) {
    return youtube_search($query);
}

// ── Helpers internos ─────────────────────────────────────────────────

function _youtube_fetch($url) {
    if (!function_exists('curl_init')) return '';

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        ),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return '';
    }

    return $response;
}

function _youtube_extract_initial_data($html) {
    if (!preg_match('/var\s+ytInitialData\s*=\s*(\{.+?\});\s*<\/script>/s', $html, $m)) {
        if (!preg_match('/var\s+ytInitialData\s*=\s*(\{.+?\});?\s*var\s+/s', $html, $m)) {
            if (!preg_match('/ytInitialData\s*=\s*(\{.+?\});/s', $html, $m)) {
                return null;
            }
        }
    }

    return json_decode($m[1], true);
}

function _youtube_parse_search_results($data) {
    $results = array();

    $contents = _youtube_array_get(
        $data,
        array('contents', 'twoColumnSearchResultsRenderer', 'primaryContents', 'sectionListRenderer', 'contents')
    );

    if (!is_array($contents)) return $results;

    foreach ($contents as $section) {
        $items = _youtube_array_get($section, array('itemSectionRenderer', 'contents'));
        if (!is_array($items)) continue;

        foreach ($items as $item) {
            $video = $item['videoRenderer'] ?? null;
            if (!$video) continue;

            $videoId = $video['videoId'] ?? '';
            if ($videoId === '') continue;

            $results[] = array(
                'video_id' => $videoId,
                'title' => _youtube_extract_text($video['title'] ?? null),
                'thumbnail' => _youtube_best_thumbnail($video['thumbnail']['thumbnails'] ?? array()),
                'channel_name' => _youtube_extract_text($video['ownerText'] ?? $video['longBylineText'] ?? null),
                'length_text' => _youtube_extract_text($video['lengthText'] ?? null),
                'view_count' => _youtube_extract_text($video['viewCountText'] ?? $video['shortViewCountText'] ?? null),
                'published_time' => _youtube_extract_text($video['publishedTimeText'] ?? null),
                'url' => 'https://www.youtube.com/watch?v=' . urlencode($videoId),
            );
        }
    }

    return $results;
}

function _youtube_parse_video_details($data, $videoId) {
    $videoDetails = array(
        'video_id' => $videoId,
        'title' => '',
        'thumbnail' => '',
        'channel_name' => '',
        'channel_id' => '',
        'length_seconds' => '',
        'view_count' => '',
        'url' => 'https://www.youtube.com/watch?v=' . urlencode($videoId),
    );

    $contents = _youtube_array_get($data, array('contents', 'twoColumnWatchNextResults', 'results', 'results', 'contents'));
    if (!is_array($contents)) {
        if (preg_match('/<title>(.+?) - YouTube<\/title>/', $html ?? '', $m)) {
            $videoDetails['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        return $videoDetails;
    }

    foreach ($contents as $item) {
        $primary = $item['videoPrimaryInfoRenderer'] ?? null;
        if ($primary) {
            $videoDetails['title'] = _youtube_extract_text($primary['title'] ?? null);
            $videoDetails['view_count'] = _youtube_extract_text($primary['viewCount']['videoViewCountRenderer']['viewCount'] ?? $primary['viewCount']['videoViewCountRenderer']['shortViewCount'] ?? null);
            $lengthStr = $primary['lengthText']['simpleText'] ?? '';
            if ($lengthStr !== '') {
                $videoDetails['length_text'] = $lengthStr;
            }
        }

        $secondary = $item['videoSecondaryInfoRenderer'] ?? null;
        if ($secondary) {
            $videoDetails['channel_name'] = _youtube_extract_text($secondary['owner']['videoOwnerRenderer']['title'] ?? null);
            $thumbnails = $secondary['owner']['videoOwnerRenderer']['thumbnail']['thumbnails'] ?? array();
            if (!empty($thumbnails)) {
                $videoDetails['channel_thumbnail'] = _youtube_best_thumbnail($thumbnails);
            }
        }
    }

    return $videoDetails;
}

// ── Utilidades ──────────────────────────────────────────────────────

function _youtube_array_get($arr, $keys) {
    $current = $arr;
    foreach ($keys as $key) {
        if (!is_array($current) || !isset($current[$key])) return null;
        $current = $current[$key];
    }
    return $current;
}

function _youtube_extract_text($obj) {
    if (!is_array($obj)) return '';
    if (isset($obj['simpleText'])) return trim((string)$obj['simpleText']);

    if (isset($obj['runs']) && is_array($obj['runs'])) {
        $text = '';
        foreach ($obj['runs'] as $run) {
            $text .= $run['text'] ?? '';
        }
        return trim($text);
    }

    return '';
}

function _youtube_best_thumbnail($thumbnails) {
    if (!is_array($thumbnails) || empty($thumbnails)) return '';
    $last = end($thumbnails);
    return $last['url'] ?? ($thumbnails[0]['url'] ?? '');
}

/**
 * Convierte el texto de published_time de YouTube (locale español) a horas.
 * Ej: "hace 2 horas" → 2, "hace 1 día" → 24, "hace 30 minutos" → 0.5.
 * Si no se puede parsear, devuelve PHP_FLOAT_MAX (muy viejo).
 */
function _youtube_parse_age_hours($publishedTime) {
    $t = mb_strtolower(trim((string)$publishedTime), 'UTF-8');
    if ($t === '') return PHP_FLOAT_MAX;

    // Números normales y "un/una" → 1
    if (preg_match('/hace\s+(\d+|un|una?)\s+(minutos?|horas?|d[ií]as?|semanas?|meses?|a[ñn]os?)/iu', $t, $m)) {
        $num = ($m[1] === 'un' || $m[1] === 'una') ? 1 : (int)$m[1];
        $unit = $m[2];

        if (preg_match('/minuto/i', $unit)) return $num / 60.0;
        if (preg_match('/hora/i',   $unit)) return (float)$num;
        if (preg_match('/d[ií]a/i',  $unit)) return $num * 24.0;
        if (preg_match('/semana/i', $unit)) return $num * 168.0;
        if (preg_match('/mes/i',    $unit)) return $num * 720.0;
        if (preg_match('/a[ñn]o/i', $unit)) return $num * 8760.0;
    }

    return PHP_FLOAT_MAX;
}

/**
 * Genera sugerencias de búsqueda usando la IA de voz (DeepSeek/OpenAI)
 * basándose en el historial de reproducción.
 */
function youtube_ai_suggest($history, $limit = 5) {
    $history = is_array($history) ? $history : array();
    $cfg = voice_ai_config();
    if (!$cfg['configured'] || !function_exists('curl_init')) {
        return array();
    }

    $historyLines = '';
    foreach (array_slice($history, -10) as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title !== '') {
            $historyLines .= '- ' . $title . "\n";
        }
    }

    if ($historyLines === '') {
        $historyLines = "(usuario nuevo, sin historial de reproduccion)";
    }

    $prompt = "Eres un recomendador musical y de videos de YouTube. El usuario ha escuchado recientemente:\n\n{$historyLines}\n\nSugiere exactamente {$limit} terminos de busqueda (en español, frases cortas de 2-5 palabras) que den buenos resultados en YouTube. Basate en su historial si hay datos utiles. Si el historial es escaso, complementa con contenido popular y tendencias actuales de YouTube España/Latinoamérica (música, noticias, deportes, entretenimiento).\n\nReglas importantes:\n- Usa frases que seguro devuelvan resultados en YouTube (no uses nombres muy raros o inventados).\n- Incluye variedad: mezcla artistas similares, generos relacionados, algun descubrimiento nuevo, y al menos una sugerencia de tendencia popular general.\n- Si el historial es muy corto o irrelevante, prioriza tendencias populares.\n\nDevuelve SOLO los terminos de busqueda, uno por linea, sin numeros ni viñetas. Sin explicaciones.";

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.8,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return array();
    }

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));

    if ($content === '') return array();

    $suggestions = array();
    foreach (explode("\n", $content) as $line) {
        $line = trim($line);
        $line = preg_replace('/^[\d\.\-\*\s]+/', '', $line);
        $line = trim($line);
        if ($line !== '' && count($suggestions) < $limit) {
            $suggestions[] = $line;
        }
    }

    return $suggestions;
}

/**
 * Usa la IA para convertir un concepto de usuario (ej: "gameplays de Minecraft")
 * en una query optimizada de YouTube (ej: "mejores gameplays minecraft espanol 2026").
 */
function youtube_ai_generate_channel_query($conceptName) {
    $conceptName = trim((string)$conceptName);
    if ($conceptName === '') return '';

    $cfg = voice_ai_config();
    if (!$cfg['configured'] || !function_exists('curl_init')) {
        // Sin IA, usar el concepto directamente
        return $conceptName;
    }

    $prompt = "Eres un optimizador de busquedas de YouTube en español. Recibes un concepto/tema corto que describe un tipo de contenido que el usuario quiere ver. Tu trabajo es transformarlo en la MEJOR query de busqueda posible para YouTube, optimizada para encontrar contenido actual y relevante.\n\nReglas:\n- La query debe ser en español.\n- Añade palabras clave como \"2026\", \"mejores\", \"ultimo\", \"actual\" cuando tenga sentido.\n- Si el concepto es un juego/videojuego, añade \"gameplay español\".\n- Si es musica, añade \"musica 2026\" o \"canciones\".\n- Si es noticias/deporte, añade \"hoy\" o \"2026\".\n- Devuelve SOLO la query final, sin comillas, sin explicaciones.\n- Maximo 8 palabras.\n\nEjemplos:\n- \"Minecraft\" -> \"mejores gameplays minecraft español 2026\"\n- \"noticias españa\" -> \"noticias españa hoy\"\n- \"reggeaton\" -> \"reggeaton 2026 mejores exitos\"\n- \"futbol\" -> \"futbol españa resumen hoy\"\n- \"cocina\" -> \"recetas cocina facil española\"\n- \"politica\" -> \"politica españa actualidad hoy\"";

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.3,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => $conceptName),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return $conceptName; // fallback: usar el concepto tal cual
    }

    $decoded = json_decode($response, true);
    $query = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));

    return $query !== '' ? $query : $conceptName;
}

/**
 * Sugiere canales tematicos basados en el historial del usuario.
 * Devuelve array de {name, query}.
 */
function youtube_ai_suggest_channels($history, $limit = 3) {
    $history = is_array($history) ? $history : array();
    $cfg = voice_ai_config();
    if (!$cfg['configured'] || !function_exists('curl_init')) {
        return array();
    }

    $historyLines = '';
    foreach (array_slice($history, -10) as $item) {
        $title = trim((string)($item['title'] ?? ''));
        if ($title !== '') {
            $historyLines .= '- ' . $title . "\n";
        }
    }

    if ($historyLines === '') {
        $historyLines = "(usuario nuevo, sin historial)";
    }

    $prompt = "Eres un curador de canales tematicos de YouTube. Basado en el historial de reproduccion del usuario, sugiere exactamente {$limit} canales tematicos que le podrian interesar.\n\nHistorial del usuario:\n{$historyLines}\n\nPara cada canal, inventa:\n1. Un NOMBRE corto y atractivo (max 3 palabras, en español)\n2. Una QUERY de busqueda optimizada para YouTube (max 8 palabras, en español) que devolveria los mejores videos actuales de ese tema\n\nDevuelvelo en formato JSON estricto:\n[\n  {\"name\": \"Nombre del canal\", \"query\": \"query optimizada youtube\"},\n  ...\n]\n\nSOLO el JSON, sin markdown ni explicaciones.";

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.9,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return array();
    }

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));

    // Limpiar markdown si lo hay
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/', '', $content);
    $content = trim($content);

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) return array();

    $channels = array();
    foreach ($parsed as $item) {
        if (!empty($item['name']) && !empty($item['query'])) {
            $channels[] = array(
                'name' => trim((string)$item['name']),
                'query' => trim((string)$item['query']),
            );
        }
        if (count($channels) >= $limit) break;
    }

    return $channels;
}

/**
 * Extrae la URL del stream de audio de un video de YouTube.
 * Para usar con Web Audio API + GainNode (amplificación real).
 * Devuelve array con 'url', 'mime_type', 'bitrate' o null si falla.
 */
function youtube_get_audio_stream($videoId) {
    $videoId = trim((string)$videoId);
    if ($videoId === '') return null;

    // YouTube ya no incluye URLs directas en el HTML — usar yt-dlp
    // (yt-dlp mantiene actualizado el protocolo de extracción de URLs)
    $cmd = sprintf(
        'yt-dlp -j --force-ipv4 --format bestaudio/best --no-playlist --no-warnings --socket-timeout 10 2>/dev/null %s',
        escapeshellarg('https://www.youtube.com/watch?v=' . $videoId)
    );
    $json = shell_exec($cmd);
    if (!is_string($json) || $json === '' || $json === null) return null;

    $info = json_decode($json, true);
    if (!$info || empty($info['formats'])) return null;

    // Buscar el mejor formato de audio puro (vcodec=none, acodec!=none)
    $best = null;
    $bestAbr = 0;
    foreach ($info['formats'] as $f) {
        if (($f['vcodec'] ?? 'none') !== 'none') continue;
        if (($f['acodec'] ?? '') === 'none') continue;
        $abr = (float)($f['abr'] ?? ($f['tbr'] ?? 0));
        if ($abr > $bestAbr) {
            $bestAbr = $abr;
            $best = $f;
        }
    }

    if (!$best || empty($best['url'])) return null;

    // Mapear extension a mime_type
    $ext = $best['ext'] ?? '';
    $mimeMap = array('m4a' => 'audio/mp4', 'webm' => 'audio/webm', 'opus' => 'audio/webm');
    $mimeType = isset($mimeMap[$ext]) ? $mimeMap[$ext] : ('audio/' . $ext);

    // Bitrate en bps (yt-dlp devuelve kbps en 'abr')
    $bitrate = (int)(($best['abr'] ?? 0) * 1000);

    return array(
        'url'            => $best['url'],
        'mime_type'      => $mimeType,
        'bitrate'        => $bitrate > 0 ? $bitrate : (int)($best['tbr'] ?? 0) * 1000,
        'content_length' => $best['filesize'] ?? ($best['filesize_approx'] ?? null),
        'duration'       => isset($info['duration']) ? (float)$info['duration'] : null,
        'format_id'      => $best['format_id'] ?? null,
    );
}

/**
 * Health check: verifica si el proxy de audio sigue funcionando.
 * Prueba con un video ID de prueba conocido.
 * Devuelve true si funciona, false si esta roto.
 */
function youtube_audio_proxy_health_check() {
    // Usamos un video ID de prueba corto y publico
    $testIds = array('jNQXAC9IVRw', 'dQw4w9WgXcQ');
    foreach ($testIds as $testId) {
        $result = youtube_get_audio_stream($testId);
        if ($result && !empty($result['url'])) {
            return true;
        }
    }
    return false;
}

/**
 * Emisoras de radio españolas en directo.
 * URLs verificadas via radio-browser.info (2026-07-23).
 */
function radio_default_stations() {
    return array(
        array('id' => 'radio_los40',       'name' => 'Los 40',         'url' => 'https://playerservices.streamtheworld.com/api/livestream-redirect/Los40.mp3',             'icon' => '🎵', 'type' => 'radio', 'freq' => 93.9),
        array('id' => 'radio_cadena100',   'name' => 'Cadena 100',     'url' => 'https://cadena100-cope.flumotion.com/chunks.m3u8',                                      'icon' => '🎶', 'type' => 'radio', 'freq' => 100.0),
        array('id' => 'radio_europafm',    'name' => 'Europa FM',      'url' => 'https://stream.zeno.fm/se76qau1hc9uv',                                                 'icon' => '🎧', 'type' => 'radio', 'freq' => 91.0),
        array('id' => 'radio_cadenaser',   'name' => 'Cadena SER',     'url' => 'https://playerservices.streamtheworld.com/api/livestream-redirect/CADENASER.mp3',        'icon' => '🗣️', 'type' => 'radio', 'freq' => 96.0),
        array('id' => 'radio_kissfm',      'name' => 'Kiss FM',        'url' => 'https://bbkissfm.kissfmradio.cires21.com/bbkissfm.mp3',                                 'icon' => '💋', 'type' => 'radio', 'freq' => 99.5),
        array('id' => 'radio_rne1',        'name' => 'RNE Radio 1',    'url' => 'https://dispatcher.rndfnk.com/crtve/rne1/main/mp3/high',                                'icon' => '📡', 'type' => 'radio', 'freq' => 88.2),
        array('id' => 'radio_rockfm',      'name' => 'Rock FM',        'url' => 'https://rockfm-cope.flumotion.com/playlist.m3u8',                                       'icon' => '🎸', 'type' => 'radio', 'freq' => 104.3),
        array('id' => 'radio_cope',        'name' => 'COPE',           'url' => 'https://net1-cope.flumotion.com/chunks.m3u8',                                           'icon' => '📰', 'type' => 'radio', 'freq' => 106.5),
    );
}

/**
 * Canales predefinidos que se siembran la primera vez que el usuario abre el reproductor.
 */
function youtube_default_channels() {
    return array(
        array('id' => 'pre_noticias',     'name' => 'Noticias de Espana',  'query' => 'noticias espana hoy 2026', 'icon' => '📰', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_politica',     'name' => 'Politica Espanola',  'query' => 'politica espana actualidad', 'icon' => '🏛️', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_deportes',     'name' => 'Deportes',            'query' => 'deportes espana resumen hoy', 'icon' => '⚽', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_futbol',       'name' => 'Futbol',              'query' => 'futbol espana ultima hora goles', 'icon' => '🏆', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_musica',       'name' => 'Musica en Espanol',  'query' => 'mejor musica en espanol 2026', 'icon' => '🎵', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_tecnologia',   'name' => 'Tecnologia',          'query' => 'tecnologia novedades 2026', 'icon' => '💻', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_economia',     'name' => 'Economia',            'query' => 'economia espana actualidad', 'icon' => '📈', 'type' => 'predefined', 'added_at' => now_datetime()),
        array('id' => 'pre_humor',        'name' => 'Humor',               'query' => 'humor espanol comedia monologos', 'icon' => '😂', 'type' => 'predefined', 'added_at' => now_datetime()),
    );
}
