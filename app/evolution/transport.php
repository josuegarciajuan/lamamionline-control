<?php
/**
 * transport.php — Resolver del selector de transporte por línea/sistema.
 *
 * Cada línea (registro de teléfono) puede operar por WAHA o por Evolution API.
 * El campo `transport` de la línea vale "waha" (default) o "evolution".
 * Estas funciones centralizan la resolución para que CRM y bot-casa consulten
 * el mismo patrón y así poder elegir el backend por línea.
 *
 * Las líneas SIEMPRE quedan vinculadas a ambos backends (2 QR); este selector
 * decide cuál responde a los mensajes. La salud se consulta en el backend activo.
 */

declare(strict_types=1);

if (!function_exists('whatsapp_transport_normalize')) {
    /**
     * Normaliza un valor de transporte a "waha" o "evolution".
     */
    function whatsapp_transport_normalize(mixed $value): string
    {
        $t = strtolower(trim((string) $value));
        return $t === 'evolution' ? 'evolution' : 'waha';
    }
}

if (!function_exists('whatsapp_transport_for')) {
    /**
     * Transporte activo de una línea (registro de teléfono).
     * Ausencia del campo ⇒ "waha" (comportamiento actual).
     *
     * @param array<string,mixed>|null $row
     */
    function whatsapp_transport_for(?array $row): string
    {
        if (!is_array($row)) {
            return 'waha';
        }
        return whatsapp_transport_normalize($row['transport'] ?? 'waha');
    }
}

if (!function_exists('whatsapp_transport_label')) {
    /**
     * Etiqueta legible de un transporte.
     */
    function whatsapp_transport_label(mixed $value): string
    {
        return whatsapp_transport_normalize($value) === 'evolution'
            ? 'Evolution API'
            : 'WAHA';
    }
}
