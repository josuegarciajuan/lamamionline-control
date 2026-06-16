<?php
/**
 * PayPalClient.php — PayPal REST API v2 wrapper (Orders).
 *
 * Pure cURL. No SDK dependency.
 * Uses config.dist.json → config.local.json (overlay) for credentials.
 */
declare(strict_types=1);

namespace WasapBot\Payment;

class PayPalClient
{
    private string $clientId;
    private string $secret;
    private string $baseUrl;
    private ?string $accessToken = null;

    /**
     * @param array<string, mixed> $config  The 'paypal' section from merged config.
     */
    public function __construct(array $config)
    {
        $this->clientId = (string) ($config['client_id'] ?? '');
        $this->secret   = (string) ($config['secret'] ?? '');
        $mode           = (string) ($config['mode'] ?? 'sandbox');
        $this->baseUrl  = ($mode === 'live')
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    // ─────────────────────────────────────────────────────────
    //  Public API
    // ─────────────────────────────────────────────────────────

    /**
     * Create a PayPal Order.
     *
     * @param float  $amount     Amount in EUR.
     * @param string $description  Order description shown to buyer.
     * @param string $returnUrl   Where to redirect after approval.
     * @param string $cancelUrl   Where to redirect if user cancels.
     * @return array{ok: bool, order_id?: string, error?: string}
     */
    public function createOrder(float $amount, string $description, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'No se pudo autenticar con PayPal.'];
        }

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format($amount, 2, '.', ''),
                ],
                'description' => $description,
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'landing_page'              => 'LOGIN',
                        'user_action'               => 'PAY_NOW',
                        'return_url'                => $returnUrl,
                        'cancel_url'                => $cancelUrl,
                    ],
                ],
            ],
        ];

        $result = $this->apiCall('POST', '/v2/checkout/orders', $payload);

        if (isset($result['id'], $result['status']) && $result['status'] === 'CREATED') {
            return ['ok' => true, 'order_id' => $result['id']];
        }

        $errorDetail = $result['message'] ?? ($result['error'] ?? 'Error desconocido al crear la orden.');
        return ['ok' => false, 'error' => $errorDetail];
    }

    /**
     * Capture (finalize) a PayPal Order after buyer approval.
     *
     * @return array{ok: bool, transaction_id?: string, error?: string}
     */
    public function captureOrder(string $orderId): array
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return ['ok' => false, 'error' => 'No se pudo autenticar con PayPal.'];
        }

        $result = $this->apiCall('POST', "/v2/checkout/orders/{$orderId}/capture");

        if (isset($result['status']) && $result['status'] === 'COMPLETED') {
            // Extract PayPal transaction ID from the capture
            $txnId = '';
            $captures = $result['purchase_units'][0]['payments']['captures'] ?? [];
            if (count($captures) > 0) {
                $txnId = (string) ($captures[0]['id'] ?? '');
            }
            return ['ok' => true, 'transaction_id' => $txnId];
        }

        $errorDetail = $result['message'] ?? ($result['error'] ?? 'Error al capturar el pago.');
        return ['ok' => false, 'error' => $errorDetail];
    }

    /**
     * Verify a PayPal webhook signature.
     *
     * @param array<string, mixed> $headers  HTTP headers of the webhook request.
     * @param string               $body     Raw request body.
     * @param string               $webhookId PayPal webhook ID.
     */
    public function verifyWebhook(array $headers, string $body, string $webhookId): bool
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return false;
        }

        $payload = [
            'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($body, true),
        ];

        $result = $this->apiCall('POST', '/v1/notifications/verify-webhook-signature', $payload);

        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }

    // ─────────────────────────────────────────────────────────
    //  Internal
    // ─────────────────────────────────────────────────────────

    private function getAccessToken(): ?string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        if ($this->clientId === '' || $this->secret === '') {
            return null;
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->secret,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data) || empty($data['access_token'])) {
            return null;
        }

        $this->accessToken = (string) $data['access_token'];
        return $this->accessToken;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function apiCall(string $method, string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        $token = $this->getAccessToken();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['error' => 'cURL error: no se pudo conectar con PayPal.'];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return ['error' => 'Respuesta inválida de PayPal (HTTP ' . $httpCode . ').'];
        }

        return $decoded;
    }
}
