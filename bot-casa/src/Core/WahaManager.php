<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * WahaManager — Gestión de instancias WAHA para líneas WhatsApp.
 *
 * Operaciones:
 *   - Crear/eliminar instancias WAHA vía SSH al servidor WAHA
 *   - Obtener QR de vinculación
 *   - Verificar estado de sesión
 *   - Enviar mensaje de prueba
 *
 * El servidor WAHA debe ser accesible vía SSH (preferiblemente Tailscale).
 * También expone la API HTTP de WAHA para consultas de estado.
 */
final class WahaManager
{
    private string $wahaServer;
    private string $wahaApiKey;
    private int $basePort;
    private string $webhookUrl;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config Config con claves:
     *   waha_server: string — IP/hostname del servidor WAHA (ej: "100.117.92.74")
     *   waha_api_key: string — API key global de WAHA
     *   waha_base_port_start: int — Puerto inicial (default 3010, va incrementando)
     *   webhook_url: string — URL del webhook (ej: "https://lamami.online/control/bot-casa/public/webhook.php")
     */
    public function __construct(array $config = [])
    {
        $this->wahaServer  = (string) ($config['waha_server'] ?? '100.117.92.74');
        $this->wahaApiKey  = (string) ($config['waha_api_key'] ?? 'local321');
        $this->basePort    = (int) ($config['waha_base_port_start'] ?? 3010);
        $this->webhookUrl  = (string) ($config['webhook_url'] ?? '');
        $this->config      = $config;
    }

    // ─────────────────────────────────────────────────────────
    //  SSH Operations (stubs — require WAHA server access)
    // ─────────────────────────────────────────────────────────

    /**
     * Crear una nueva instancia WAHA para una línea.
     * Asigna un puerto único, configura el webhook y arranca el contenedor.
     *
     * @param string $phoneNumber Número de teléfono (last9)
     * @param int    $userId      ID del usuario propietario
     * @return array{ok: bool, port?: int, error?: string}
     */
    public function createInstance(string $phoneNumber, int $userId): array
    {
        // Asignar puerto único basado en phoneNumber
        $port = $this->basePort + (int) (crc32($phoneNumber) % 1000);

        $instanceDir = "/srv/waha_{$port}";

        // Construir docker-compose.yml
        $compose = <<<YAML
services:
  waha{$port}:
    image: devlikeapro/waha:latest
    container_name: waha{$port}
    restart: unless-stopped
    ports:
      - "{$port}:3000"
    pids_limit: -1
    ulimits:
      nproc: 65535
      nofile:
        soft: 65535
        hard: 65535
    security_opt:
      - seccomp=unconfined
    environment:
      - WAHA_API_KEY={$this->wahaApiKey}
      - WHATSAPP_DEFAULT_ENGINE=GOWS
      - TZ=Europe/Madrid
      - WAHA_DASHBOARD_USERNAME=admin
      - WAHA_DASHBOARD_PASSWORD=admin123
      - WHATSAPP_SWAGGER_USERNAME=admin
      - WHATSAPP_SWAGGER_PASSWORD=admin123
      - WHATSAPP_HOOK_URL={$this->webhookUrl}
      - WHATSAPP_HOOK_EVENTS=message
    volumes:
      - ./data:/app/data
      - ./sessions:/app/.sessions
      - ./media:/app/.media
YAML;

        $commands = [
            "mkdir -p {$instanceDir}",
            "echo " . escapeshellarg($compose) . " > {$instanceDir}/docker-compose.yml",
            "cd {$instanceDir} && docker compose down 2>/dev/null; docker compose up -d",
        ];

        $cmd = 'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 ' . escapeshellarg($this->wahaServer)
             . ' ' . escapeshellarg(implode(' && ', $commands));

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            return ['ok' => false, 'error' => 'SSH failed: ' . implode("\n", $output)];
        }

        // Configurar sesión default con webhook
        $this->configureSession($port, 'default', $this->webhookUrl);

        return ['ok' => true, 'port' => $port];
    }

    /**
     * Configurar la sesión WAHA con el webhook.
     */
    private function configureSession(int $port, string $session, string $webhookUrl): void
    {
        $baseUrl = "http://{$this->wahaServer}:{$port}";
        $apiKey  = $this->wahaApiKey;

        // PUT /api/sessions/{session}
        $payload = json_encode([
            'name'   => $session,
            'config' => [
                'webhooks' => [
                    ['url' => $webhookUrl, 'events' => ['message']],
                ],
            ],
        ]);

        $ch = curl_init("{$baseUrl}/api/sessions/{$session}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Api-Key: {$apiKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Obtener QR de vinculación para una línea.
     *
     * @return array{ok: bool, qr_base64?: string, error?: string}
     */
    public function getQrCode(int $port): array
    {
        $baseUrl = "http://{$this->wahaServer}:{$port}";
        $apiKey  = $this->wahaApiKey;

        $ch = curl_init("{$baseUrl}/api/default/auth/qr?format=image");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                "X-Api-Key: {$apiKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return ['ok' => false, 'error' => "HTTP {$httpCode}: Failed to get QR"];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['data'])) {
            return ['ok' => false, 'error' => 'No QR data in response'];
        }

        // Devolver el base64 directamente (el frontend lo convierte a imagen)
        return ['ok' => true, 'qr_base64' => (string) $data['data']];
    }

    /**
     * Verificar el estado de una sesión WAHA.
     *
     * @return array{ok: bool, status?: string, error?: string}
     */
    public function checkStatus(int $port): array
    {
        $baseUrl = "http://{$this->wahaServer}:{$port}";
        $apiKey  = $this->wahaApiKey;

        $ch = curl_init("{$baseUrl}/api/sessions/default");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                "X-Api-Key: {$apiKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return ['ok' => false, 'status' => 'down', 'error' => "HTTP {$httpCode}"];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['ok' => true, 'status' => 'unknown'];
        }

        $status = (string) ($data['status'] ?? $data['me']['status'] ?? 'unknown');
        return ['ok' => true, 'status' => $status];
    }

    /**
     * Enviar un mensaje de prueba.
     *
     * @return array{ok: bool, error?: string}
     */
    public function sendTestMessage(int $port, string $chatId, string $text): array
    {
        $baseUrl = "http://{$this->wahaServer}:{$port}";
        $apiKey  = $this->wahaApiKey;

        $payload = json_encode([
            'session' => 'default',
            'chatId'  => $chatId,
            'text'    => $text,
        ]);

        $ch = curl_init("{$baseUrl}/api/sendText");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Api-Key: {$apiKey}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'error' => "HTTP {$httpCode}"];
        }

        return ['ok' => true];
    }

    /**
     * Eliminar una instancia WAHA.
     *
     * @return array{ok: bool, error?: string}
     */
    public function deleteInstance(int $port): array
    {
        $instanceDir = "/srv/waha_{$port}";
        $cmd = 'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 ' . escapeshellarg($this->wahaServer)
             . ' ' . escapeshellarg("cd {$instanceDir} && docker compose down && rm -rf {$instanceDir}");

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            return ['ok' => false, 'error' => implode("\n", $output)];
        }

        return ['ok' => true];
    }
}
