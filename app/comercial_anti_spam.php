<?php
/**
 * comercial_anti_spam.php — Sistema anti-repetición de mensajes de apertura.
 *
 * WhatsApp banea cuentas que envían mensajes repetidos o muy similares.
 * Este módulo evita que el LLM genere aperturas parecidas a las recientes.
 *
 * Estrategia:
 *   1. Cache de últimas N aperturas por negocio (storage JSON)
 *   2. Antes de generar: inyectar como ejemplos NEGATIVOS en el prompt
 *   3. Después de generar: verificar similitud con caché
 *   4. Si demasiado similar → regenerar con instrucción más fuerte
 *   5. Guardar nueva apertura en caché
 */

declare(strict_types=1);

// Configuración
define('COMERCIAL_ANTI_SPAM_MAX_OPENERS', 50);       // Máximo de aperturas en caché por negocio
define('COMERCIAL_ANTI_SPAM_INJECT_COUNT', 30);       // Cuántas inyectar en el prompt como negativas
define('COMERCIAL_ANTI_SPAM_SIMILARITY_THRESHOLD', 60); // % de similitud máxima permitida

// ═══════════════════════════════════════════════════════════════
//  CACHÉ DE APERTURAS
// ═══════════════════════════════════════════════════════════════

/**
 * Devuelve las aperturas recientes de un negocio.
 *
 * @param string $processSlug Slug del negocio
 * @param int    $limit       Cuántas devolver
 * @return array              Array de strings con textos de apertura
 */
function comercial_anti_spam_recent_openers(string $processSlug, int $limit = 30): array {
    $all = comercial_anti_spam_cache_read();
    $openers = $all[$processSlug] ?? array();

    // Devolver las más recientes (últimas del array)
    $openers = array_reverse($openers);
    if (count($openers) > $limit) {
        $openers = array_slice($openers, 0, $limit);
    }

    return $openers;
}

/**
 * Guarda una nueva apertura en el caché.
 */
function comercial_anti_spam_store_opener(string $processSlug, string $text): void {
    $text = trim($text);
    if ($text === '') return;

    $all = comercial_anti_spam_cache_read();

    if (!isset($all[$processSlug])) {
        $all[$processSlug] = array();
    }

    $all[$processSlug][] = $text;

    // Limitar a MAX_OPENERS por negocio
    while (count($all[$processSlug]) > COMERCIAL_ANTI_SPAM_MAX_OPENERS) {
        array_shift($all[$processSlug]);
    }

    comercial_anti_spam_cache_write($all);
}

/**
 * Verifica si un texto es demasiado similar a aperturas recientes.
 * Devuelve el % de similitud más alto encontrado (0-100).
 */
function comercial_anti_spam_check_similarity(string $processSlug, string $text): int {
    $recent = comercial_anti_spam_recent_openers($processSlug, COMERCIAL_ANTI_SPAM_MAX_OPENERS);
    if (empty($recent)) return 0;

    $textFolded = comercial_anti_spam_fold($text);
    $maxSimilarity = 0;

    foreach ($recent as $opener) {
        $openerFolded = comercial_anti_spam_fold($opener);
        $similarity = comercial_anti_spam_similarity($textFolded, $openerFolded);
        if ($similarity > $maxSimilarity) {
            $maxSimilarity = $similarity;
        }
    }

    return (int)$maxSimilarity;
}

/**
 * Verifica si el texto es aceptable (no demasiado similar a recientes).
 */
function comercial_anti_spam_is_acceptable(string $processSlug, string $text): bool {
    $similarity = comercial_anti_spam_check_similarity($processSlug, $text);
    return $similarity < COMERCIAL_ANTI_SPAM_SIMILARITY_THRESHOLD;
}

// ═══════════════════════════════════════════════════════════════
//  SIMILITUD DE TEXTO
// ═══════════════════════════════════════════════════════════════

/**
 * Calcula similitud entre dos textos (0-100).
 * Usa similar_text() de PHP (rápido, nativo).
 */
function comercial_anti_spam_similarity(string $a, string $b): int {
    if ($a === '' || $b === '') return 0;
    if ($a === $b) return 100;

    similar_text($a, $b, $percent);
    return (int)$percent;
}

/**
 * Normaliza texto para comparación (fold).
 */
function comercial_anti_spam_fold(string $text): string {
    $text = function_exists('mb_strtolower')
        ? mb_strtolower(trim($text), 'UTF-8')
        : strtolower(trim($text));

    // Eliminar emojis para comparación (no deberían ser el factor diferencial)
    $text = preg_replace('/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u', '', $text);

    // Normalizar espacios
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

// ═══════════════════════════════════════════════════════════════
//  PERSISTENCIA (storage JSON)
// ═══════════════════════════════════════════════════════════════

function comercial_anti_spam_cache_file(): string {
    return __DIR__ . '/../data/comercial_anti_spam_cache.json';
}

function comercial_anti_spam_cache_read(): array {
    $file = comercial_anti_spam_cache_file();
    if (!file_exists($file)) {
        return array();
    }
    $content = file_get_contents($file);
    if ($content === false) {
        return array();
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : array();
}

function comercial_anti_spam_cache_write(array $data): void {
    $file = comercial_anti_spam_cache_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
