<?php

declare(strict_types=1);

namespace WasapBot\Payment;

use WasapBot\Core\Config;
use WasapBot\Core\UserManager;
use WasapBot\Core\WahaManager;

/**
 * PaymentConfirmationNotifier — envía un WhatsApp de confirmación tras un pago
 * correcto, desde la línea configurada (p. ej. la línea "jostal dulce") al
 * teléfono del usuario que ha pagado.
 *
 * Configuración (config: payment_confirmation_whatsapp):
 *   - enabled:            bool — activa/desactiva el envío
 *   - from_port:          int  — puerto WAHA de la línea emisora
 *   - from_phone:         string — teléfono de la línea emisora (solo informativo)
 *   - to_phone_override:  string — destino fijo; si está vacío se usa el username
 *                                  del usuario (que en CasaWasap es su teléfono)
 *   - message:            string — plantilla con {name}, {amount}, {days}
 *
 * Es BEST-EFFORT: cualquier fallo (config, red, WAHA) se registra en error_log
 * y NUNCA rompe el flujo de pago.
 */
final class PaymentConfirmationNotifier
{
    public function __construct(
        private readonly Config $config,
        private readonly UserManager $userManager,
    ) {
    }

    /**
     * Envía (si está habilitado) el WhatsApp de confirmación del pago.
     */
    public function notify(int $userId, float $amount): void
    {
        try {
            $settings = $this->config->get('payment_confirmation_whatsapp', []);
            if (!is_array($settings) || empty($settings['enabled'])) {
                return;
            }

            $target = $this->resolveTargetPhone($userId, $settings);
            if ($target === '') {
                return;
            }

            $chatId = self::buildChatId($target);
            if ($chatId === '') {
                return;
            }

            $template = (string) ($settings['message'] ?? '');
            if ($template === '') {
                return;
            }

            $user = $this->userManager->getUser($userId);
            $name = (string) (($user['name'] ?? '') !== '' ? $user['name'] : ($user['username'] ?? 'Cliente'));
            $text = self::formatMessage($template, $name, $amount);

            $port = (int) ($settings['from_port'] ?? 0);
            if ($port <= 0) {
                return;
            }

            $waha = new WahaManager();
            $result = $waha->sendTestMessage($port, $chatId, $text);
            if (empty($result['ok'])) {
                error_log(
                    '[PaymentConfirmationNotifier] send failed user=' . $userId
                    . ' port=' . $port
                    . ' err=' . (string) ($result['error'] ?? 'unknown')
                );
            }
        } catch (\Throwable $e) {
            error_log('[PaymentConfirmationNotifier] error: ' . $e->getMessage());
        }
    }

    /**
     * Resuelve el teléfono destino: override de config o username del usuario
     * (solo si es numérico). Devuelve '' si no hay destino válido.
     *
     * @param array<mixed> $settings
     */
    public function resolveTargetPhone(int $userId, array $settings): string
    {
        $override = self::normalizeDigits((string) ($settings['to_phone_override'] ?? ''));
        if ($override !== '') {
            return $override;
        }

        $user = $this->userManager->getUser($userId);
        if ($user === null) {
            return '';
        }

        return self::normalizeDigits((string) ($user['username'] ?? ''));
    }

    /**
     * Deja solo los dígitos de un teléfono.
     */
    public static function normalizeDigits(string $raw): string
    {
        return preg_replace('/[^0-9]/', '', $raw) ?? '';
    }

    /**
     * Construye el chatId WAHA (formato internacional): si el número es un móvil
     * español de 9 dígitos (empieza por 6/7) se añade el prefijo 34.
     */
    public static function buildChatId(string $phone): string
    {
        $digits = self::normalizeDigits($phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits) === 1) {
            $digits = '34' . $digits;
        }
        return $digits . '@c.us';
    }

    /**
     * Sustituye {name}, {amount} y {days} en la plantilla del mensaje.
     */
    public static function formatMessage(string $template, string $name, float $amount, int $days = 7): string
    {
        $replace = [
            '{name}'   => $name,
            '{amount}' => number_format($amount, 2, ',', ''),
            '{days}'   => (string) $days,
        ];
        return strtr($template, $replace);
    }
}
