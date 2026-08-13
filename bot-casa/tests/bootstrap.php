<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap — same autoloader as webhook.php.
 */

define('WASAPBOT_ROOT', dirname(__DIR__));

$vendorAutoload = WASAPBOT_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'WasapBot\\';
        $prefixLen = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLen) !== 0) return;
        $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

// ── Manually load test support files (PSR-4 incompatible: multiple classes per file) ──
require_once WASAPBOT_ROOT . '/tests/Support/Fakes.php';
require_once WASAPBOT_ROOT . '/tests/Support/TmpEnv.php';
require_once WASAPBOT_ROOT . '/tests/Support/PayloadFactory.php';

// ── Polyfill for Normalizer class and normalizer_normalize (intl extension) ──
if (!class_exists('Normalizer', false)) {
    class Normalizer
    {
        public const int NFKD = 2;
        public const int NFC  = 1;
        public const int NFD  = 4;
    }
}
if (!function_exists('normalizer_normalize')) {
    function normalizer_normalize(string $input, int $form = 2): string|false
    {
        if ($input === '') return '';
        // Accent-stripping only — preserves all non-accent characters (€, digits, etc.)
        static $map = null;
        if ($map === null) {
            $map = [
                'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
                'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Æ' => 'AE',
                'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
                'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
                'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
                'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
                'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
                'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ø' => 'O', 'Œ' => 'OE',
                'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
                'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
                'ñ' => 'n', 'Ñ' => 'N',
                'ç' => 'c', 'Ç' => 'C',
                'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y', 'Ÿ' => 'Y',
            ];
        }
        return strtr($input, $map);
    }
}
