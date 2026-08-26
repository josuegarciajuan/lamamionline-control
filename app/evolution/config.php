<?php
/**
 * config.php — Configuración y resolución del cliente Evolution por línea.
 *
 * Lee el host y la API key de Evolution desde data/evolution_config.json
 * (gitignored, no se commitea) y construye un EvolutionApi por línea.
 *
 * data/evolution_config.json:
 *   { "host": "http://100.117.92.74:8081", "api_key": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/EvolutionApi.php';

if (!function_exists('evolution_config')) {
    /**
     * @return array{host:string,api_key:string}
     */
    function evolution_config(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [
            'host' => EvolutionApi::DEFAULT_HOST,
            'api_key' => '',
        ];
        $path = defined('DATA_PATH') ? DATA_PATH . '/evolution_config.json' : '';
        if ($path !== '' && is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            if (is_array($data)) {
                if (!empty($data['host'])) $cache['host'] = rtrim((string) $data['host'], '/');
                if (!empty($data['api_key'])) $cache['api_key'] = (string) $data['api_key'];
            }
        }
        return $cache;
    }
}

if (!function_exists('evolution_instance_name')) {
    /**
     * Nombre de instancia Evolution para una línea (registro de teléfono).
     * Se usa el campo explícito evo_instance si existe; si no, se deriva del nombre.
     *
     * @param array<string,mixed> $row
     */
    function evolution_instance_name(array $row): string
    {
        $name = trim((string) ($row['evo_instance'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['nombre'] ?? ''));
        }
        $name = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]/', '', $name));
        if ($name === '') {
            $name = 'linea_' . ($row['id'] ?? 'linea');
        }
        return $name;
    }
}

if (!function_exists('evolution_client_for_row')) {
    /**
     * Construye un EvolutionApi para una línea.
     *
     * @param array<string,mixed>|null $row
     */
    function evolution_client_for_row(?array $row): EvolutionApi
    {
        $cfg = evolution_config();
        $row = is_array($row) ? $row : [];
        return new EvolutionApi(
            $cfg['host'],
            $cfg['api_key'],
            evolution_instance_name($row),
            20,
            '34'
        );
    }
}

if (!function_exists('evolution_webhook_url_for_row')) {
    /**
     * URL del webhook receptor de Evolution según el uso de la línea.
     * Devuelve '' si la línea aún no tiene webhook Evolution configurado.
     *
     * @param array<string,mixed> $row
     */
    function evolution_webhook_url_for_row(array $row): string
    {
        $uso = strtolower(trim((string)($row['uso'] ?? '')));
        if ($uso === 'personal') {
            return 'http://100.76.30.118/control/personal_wasap_webhook_evo.php';
        }
        if ($uso === 'bot casa') {
            return 'http://100.76.30.118/control/bot-casa/public/webhook_evo.php';
        }
        // comercial / resto
        return 'http://100.76.30.118/comercial_webhook_evo.php';
    }
}

if (!function_exists('evolution_ensure_webhook')) {
    /**
     * Garantiza que la instancia Evolution de una línea exista y tenga su webhook
     * configurado. Idempotente; se llama desde QR, restart y health (self-heal)
     * para que ningún webhook quede en el olvido.
     *
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    function evolution_ensure_webhook(?array $row): array
    {
        $row = is_array($row) ? $row : [];
        $url = evolution_webhook_url_for_row($row);
        if ($url === '') {
            return ['ok' => true, 'note' => 'sin webhook definido para este uso'];
        }
        $client = evolution_client_for_row($row);
        // Asegurar instancia
        $st = $client->connectionState();
        if (!$st['ok']) {
            $client->createInstance($client->instanceName(), true);
            usleep(1200000);
        }
        $r = $client->setWebhook($url);
        return [
            'ok' => $r['ok'],
            'instance' => $client->instanceName(),
            'url' => $url,
            'http_code' => $r['http_code'] ?? 0,
            'error' => $r['error'] ?? null,
        ];
    }
}
