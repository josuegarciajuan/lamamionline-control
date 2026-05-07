<?php

require_once __DIR__ . '/destacamos.php';

function subirGratis(array $payload): array
{
    return destacamos_subir_gratis($payload);
}
