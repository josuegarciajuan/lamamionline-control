<?php
/**
 * Regresiones de medios comerciales con fixtures sin datos de producción.
 * Uso: php tools/test_comercial_media_regressions.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

function comercial_media_assert_same($expected, $actual, string $label): bool
{
    $ok = $expected === $actual;
    fwrite($ok ? STDOUT : STDERR, ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $ok;
}

$pass = true;

$shortlink = 'https://compartir.site/codigo-fixture/';
$pass = comercial_media_assert_same(
    'https://compartir.site/codigo-fixture/codigo-fixture.jpg',
    comercial_direct_image_url($shortlink),
    'shortlink compartir.site usa JPG directo exclusivamente como src'
) && $pass;
$pass = comercial_media_assert_same(
    'https://public.example.test/photo.webp?size=large',
    comercial_direct_image_url('https://public.example.test/photo.webp?size=large'),
    'una URL de imagen pública normal no se modifica'
) && $pass;

$evolution = comercial_inbound_media_info([
    'instance' => 'instance-fixture',
    'message_id' => 'message-fixture',
    'raw' => [
        'message' => [
            'imageMessage' => [
                'url' => 'https://minio.example.test/object-fixture',
                'mimetype' => 'image/jpeg',
                'fileName' => 'image-fixture.jpg',
                'caption' => 'caption-fixture',
            ],
        ],
    ],
]);
$pass = comercial_media_assert_same('instance-fixture', $evolution['instance'] ?? null, 'Evolution conserva la instancia para recuperación autenticada') && $pass;
$pass = comercial_media_assert_same('message-fixture', $evolution['message_id'] ?? null, 'Evolution conserva el identificador del mensaje') && $pass;
$pass = comercial_media_assert_same('caption-fixture', $evolution['caption'] ?? null, 'Evolution conserva el caption del medio') && $pass;

$waha = comercial_inbound_media_info([
    'raw' => [
        'media' => [
            'url' => 'https://media.waha.example.test/image-fixture.jpg',
            'mimetype' => 'image/jpeg',
        ],
    ],
]);
$pass = comercial_media_assert_same('image', $waha['type'] ?? null, 'WAHA clasifica una imagen como image') && $pass;
$pass = comercial_media_assert_same('https://media.waha.example.test/image-fixture.jpg', $waha['url'] ?? null, 'WAHA conserva URL pública directa sin enviarla al proxy') && $pass;

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS') . PHP_EOL);
exit($pass ? 0 : 1);
