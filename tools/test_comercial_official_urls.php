<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/comercial_knowledge.php';
require_once dirname(__DIR__) . '/app/comercial_knowledge_v2.php';
require_once dirname(__DIR__) . '/app/comercial_agent.php';

function assert_same_value($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function assert_true_value(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assert_same_value('https://casawasap.com', comercial_official_url_for_slug('casawasap'), 'CasaWasap debe resolver su URL oficial');
assert_same_value('https://lamami.online', comercial_official_url_for_slug('lamami'), 'LaMami debe resolver su URL oficial');
assert_same_value('https://shhexxchollos.com', comercial_official_url_for_slug('shhexxchollos'), 'Shhexxchollos debe resolver su URL oficial');

assert_true_value(
    comercial_official_url_was_shared(
        array(array('direction' => 'out', 'text' => 'Puedes verlo en https://casawasap.com')),
        'https://casawasap.com'
    ),
    'El historial debe detectar una URL oficial ya compartida'
);
assert_true_value(
    !comercial_official_url_was_shared(
        array(array('direction' => 'in', 'text' => '¿Cómo funciona?')),
        'https://casawasap.com'
    ),
    'El historial no debe marcar como compartida una URL ausente'
);

$opener = comercial_ensure_official_url_in_opener('CasaWasap contesta el WhatsApp de tu casa 24/7.', 'https://casawasap.com');
assert_true_value(str_contains($opener, 'https://casawasap.com'), 'El backstop debe añadir la URL oficial ausente');
assert_same_value(
    'Ya te la había pasado: https://casawasap.com',
    comercial_ensure_official_url_in_opener('Ya te la había pasado: https://casawasap.com', 'https://casawasap.com'),
    'El backstop no debe duplicar la URL'
);

assert_true_value(
    comercial_reply_url_guidance('https://casawasap.com', false, '¿Qué incluye?') !== '',
    'Debe existir orientación contextual cuando la URL aún no se ha compartido'
);
assert_same_value(
    '',
    comercial_reply_url_guidance('https://casawasap.com', true, '¿Qué incluye?'),
    'No debe sugerirse repetir la URL si ya se compartió'
);
assert_same_value(
    '',
    comercial_reply_url_guidance('https://casawasap.com', false, 'No me interesa, gracias'),
    'No debe sugerirse la URL ante un rechazo explícito'
);

fwrite(STDOUT, "OK comercial official URLs\n");
