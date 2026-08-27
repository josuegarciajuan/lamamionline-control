<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * WahaManager — Gestión de instancias WAHA vía HTTP API.
 *
 * Dos fuentes:
 *   1. WAHA Manager API (http://waha-server/wahaapi/) — CRUD de contenedores docker
 *   2. WAHA HTTP API (http://waha-server:{port}/api/...) — gestión de sesiones
 */
final class WahaManager implements LineProvisioningWahaInterface
{
    private string $wahaServer;
    private string $wahaApiKey;
    private string $managerBaseUrl;
    private string $webhookUrl;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $this->wahaServer    = (string) ($config['waha_server'] ?? '100.117.92.74');
        $this->wahaApiKey    = (string) ($config['waha_api_key'] ?? 'local321');
        $this->managerBaseUrl = "http://{$this->wahaServer}/wahaapi";
        $this->webhookUrl    = (string) ($config['webhook_url'] ?? 'https://lamami.online/control/bot-casa/public/webhook.php');
    }

    // ─────────────────────────────────────────────────────────
    //  Manager API (container CRUD)
    // ─────────────────────────────────────────────────────────

    /** @return array */
    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        return $this->managerGet('status');
    }

    /** @return array */
    public function getNextPort(): int
    {
        $s = $this->getStatus();
        return (int) ($s['next_port'] ?? 3020);
    }

    /** @return array */
    /** @return array<string, mixed> */
    public function createInstance(int $port = 0): array
    {
        return $this->managerPost('create', ['port' => $port > 0 ? $port : $this->getNextPort()]);
    }

    /** @return array */
    /** @return array<string, mixed> */
    public function deleteInstance(int $port): array
    {
        return $this->managerPost('delete', ['port' => $port]);
    }

    /** @return array */
    /** @return array<string, mixed> */
    public function resetInstance(int $port): array
    {
        return $this->managerPost('reset', ['port' => $port]);
    }

    /** @return array */
    /** @return array<string, mixed> */
    public function startSession(int $port): array
    {
        return $this->managerPost('start_session', ['port' => $port]);
    }

    // ─────────────────────────────────────────────────────────
    //  WAHA HTTP API (session management)
    // ─────────────────────────────────────────────────────────

    /** @return array{ok: bool, qr_base64?: string, error?: string} */
    public function getQrCode(int $port): array
    {
        $url = "http://{$this->wahaServer}:{$port}/api/default/auth/qr?format=image";
        $lastError = '';

        // Retry up to 5 times — the Docker container may still be booting
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Accept: application/json', "X-Api-Key: {$this->wahaApiKey}"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response !== false) {
                $data = json_decode((string) $response, true);
                if (is_array($data) && !empty($data['data'])) {
                    return ['ok' => true, 'qr_base64' => (string) $data['data']];
                }
                $lastError = 'No QR data in response';
            } elseif ($response === false) {
                $lastError = "WAHA no responde en puerto {$port} (intento {$attempt}/5)";
                if ($curlError !== '') {
                    $lastError .= ': ' . $curlError;
                }
            } else {
                $lastError = "HTTP {$httpCode}: Failed to get QR";
            }

            if ($attempt < 5) {
                sleep(1);
            }
        }
        return ['ok' => false, 'error' => $lastError];
    }

    /** @return array{ok: bool, status?: string, phone?: string, error?: string} */
    public function checkStatus(int $port): array
    {
        $url = "http://{$this->wahaServer}:{$port}/api/sessions/default";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Accept: application/json', "X-Api-Key: {$this->wahaApiKey}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return ['ok' => false, 'status' => 'down', 'error' => "HTTP {$httpCode}"];
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) return ['ok' => true, 'status' => 'unknown'];

        $status = (string) ($data['status'] ?? 'unknown');
        $phone  = isset($data['me']['id']) ? str_replace('@c.us', '', (string) $data['me']['id']) : '';
        return ['ok' => true, 'status' => $status, 'phone' => $phone];
    }

    /** @return array{ok: bool, error?: string} */
    public function configureSession(int $port, string $webhookUrl): array
    {
        $url = "http://{$this->wahaServer}:{$port}/api/sessions/default";
        $payload = json_encode([
            'name' => 'default',
            'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', "X-Api-Key: {$this->wahaApiKey}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $httpCode >= 200 && $httpCode < 300];
    }

    /** @return array{ok: bool, http_code: int, error?: string} */
    public function sendTestMessage(int $port, string $chatId, string $text): array
    {
        $url = "http://{$this->wahaServer}:{$port}/api/sendText";
        $payload = json_encode(['session' => 'default', 'chatId' => $chatId, 'text' => $text]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', "X-Api-Key: {$this->wahaApiKey}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $result = ['ok' => $httpCode >= 200 && $httpCode < 300, 'http_code' => $httpCode];
        if ($response !== false && $response !== '') {
            $body = @json_decode((string) $response, true);
            if (is_array($body) && isset($body['error'])) {
                $result['error'] = (string) $body['error'];
            }
        }
        if ($curlErr !== '') {
            $result['error'] = ($result['error'] ?? '') ? $result['error'] . ' | curl: ' . $curlErr : 'curl: ' . $curlErr;
        }
        return $result;
    }

    /** @return array List of available WAHA ports with status */
    /** @return array<int, array<string, mixed>> */
    public function scanInstances(): array
    {
        $status = $this->getStatus();
        $ports = [];

        if (!empty($status['api_ports'])) {
            foreach ($status['api_ports'] as $ap) {
                $ports[(int)$ap['port']] = [
                    'port' => (int)$ap['port'],
                    'sessions' => $ap['sessions'] ?? [],
                    'working' => !empty($ap['sessions']),
                    'phone' => $ap['sessions'][0]['phone'] ?? '',
                ];
            }
        }
        return $ports;
    }

    // ─────────────────────────────────────────────────────────
    //  Internal HTTP helpers
    // ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function managerGet(string $action): array
    {
        $url = "{$this->managerBaseUrl}/?action={$action}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Accept: application/json', "X-Api-Key: {$this->wahaApiKey}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = @json_decode((string) $resp, true);
        return is_array($data) ? $data : ['ok' => false, 'error' => 'Invalid response'];
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    private function managerPost(string $action, array $fields): array
    {
        $url = "{$this->managerBaseUrl}/?action={$action}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Accept: application/json', "X-Api-Key: {$this->wahaApiKey}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = @json_decode((string) $resp, true);
        return is_array($data) ? $data : ['ok' => false, 'error' => 'Invalid response: ' . substr((string)$resp, 0, 200)];
    }
}
