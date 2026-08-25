<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * SubscriptionManager — Gestión de suscripciones, trials y pagos para bot-casa.
 *
 * Trabaja con los datos de usuario en users.json (campos: subscription_status,
 * trial_start, trial_end, subscription_start, subscription_end, subscription_type,
 * payments).
 *
 * Estados posibles:
 *   - unlimited: acceso sin restricciones (legacy, admin, pruebas)
 *   - demo:      usuario demo (acceso pero sin cambios)
 *   - trial:     periodo de prueba gratuito (10 días)
 *   - active:    plan pagado activo
 *   - expired:   trial o suscripción expirados
 */
final class SubscriptionManager
{
    private UserManager $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    // ─────────────────────────────────────────────────────────
    //  Status & Access
    // ─────────────────────────────────────────────────────────

    /**
     * Obtiene el estado completo de la suscripción de un usuario.
     *
     * @return array{status: string, currentDay: int, totalDays: int, daysLeft: int, isExpired: bool, periodLabel: string, canUseBot: bool, subscriptionEnd: string|null}
     */
    public function getStatus(int $userId): array
    {
        $user = $this->userManager->getUser($userId);
        if ($user === null) {
            return $this->defaultStatus('expired');
        }

        $status = (string) ($user['subscription_status'] ?? '');
        $isDemo = (($user['username'] ?? '') === 'demo');

        // Demo user
        if ($isDemo) {
            return [
                'status' => 'demo',
                'currentDay' => 0,
                'totalDays' => 0,
                'daysLeft' => 0,
                'isExpired' => false,
                'periodLabel' => 'Modo demostración',
                'canUseBot' => true,
                'subscriptionEnd' => null,
            ];
        }

        // Legacy users or users without subscription field → unlimited
        if ($status === '' || $status === 'unlimited') {
            return [
                'status' => 'unlimited',
                'currentDay' => 0,
                'totalDays' => 0,
                'daysLeft' => 0,
                'isExpired' => false,
                'periodLabel' => 'Acceso completo',
                'canUseBot' => true,
                'subscriptionEnd' => null,
            ];
        }

        // Admin always has unlimited access
        if (($user['role'] ?? '') === 'admin') {
            return [
                'status' => 'unlimited',
                'currentDay' => 0,
                'totalDays' => 0,
                'daysLeft' => 0,
                'isExpired' => false,
                'periodLabel' => 'Acceso completo',
                'canUseBot' => true,
                'subscriptionEnd' => null,
            ];
        }

        // User is inactive → expired
        if (empty($user['active'])) {
            return $this->defaultStatus('expired');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));

        switch ($status) {
            case 'trial':
                return $this->computeTrialStatus($user, $now);

            case 'active':
                return $this->computeActiveStatus($user, $now);

            case 'expired':
                return $this->defaultStatus('expired');

            default:
                return $this->defaultStatus('expired');
        }
    }

    /**
     * ¿Puede este usuario usar el bot?
     */
    public function canUseBot(int $userId): bool
    {
        $status = $this->getStatus($userId);
        return $status['canUseBot'];
    }

    /**
     * ¿Está expirado el acceso de este usuario?
     */
    public function isExpired(int $userId): bool
    {
        $status = $this->getStatus($userId);
        return $status['isExpired'];
    }

    /**
     * Indica si el usuario tiene al menos un pago persistido en su historial.
     * Este criterio no depende del estado actual de la suscripción.
     */
    public function hasHistoricalPayment(int $userId): bool
    {
        $user = $this->userManager->getUser($userId);
        $payments = $user['payments'] ?? null;

        return is_array($payments) && $payments !== [];
    }

    // ─────────────────────────────────────────────────────────
    //  Trial
    // ─────────────────────────────────────────────────────────

    /**
     * Inicia el periodo de prueba para un usuario.
     */
    public function startTrial(int $userId): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $trialEnd = $now->modify('+10 days');

        $result = $this->userManager->updateUser($userId, [
            'subscription_status' => 'trial',
            'trial_start' => $now->format('c'),
            'trial_end' => $trialEnd->format('c'),
            'subscription_start' => null,
            'subscription_end' => null,
            'subscription_type' => null,
        ]);

        return $result['ok'];
    }

    // ─────────────────────────────────────────────────────────
    //  Payment & Activation
    // ─────────────────────────────────────────────────────────

    /**
     * Activa o renueva el plan semanal.
     * El periodo pagado empieza AHORA (sobrescribe cualquier trial restante).
     * Si ya está activo, se añaden semanas al final del periodo actual.
     *
     * @param int $weeks Número de semanas a añadir (por defecto 1)
     *
     * @return array{ok: bool, error?: string}
     */
    public function activateWeekly(int $userId, int $weeks = 1): array
    {
        if ($weeks < 1) {
            return ['ok' => false, 'error' => 'El número de semanas debe ser al menos 1.'];
        }

        $user = $this->userManager->getUser($userId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        $currentStatus = (string) ($user['subscription_status'] ?? '');

        // Determinar el inicio del nuevo periodo: siempre empieza ahora.
        // Si el usuario ya está activo y su suscripción aún no ha vencido,
        // añadimos semanas al final del periodo actual.
        if ($currentStatus === 'active') {
            $subEnd = (string) ($user['subscription_end'] ?? '');
            if ($subEnd !== '') {
                try {
                    $start = new \DateTimeImmutable($subEnd);
                    if ($start < $now) {
                        $start = $now;
                    }
                } catch (\Exception) {
                    $start = $now;
                }
            } else {
                $start = $now;
            }
        } else {
            // Expirado o unlimited → empezar ahora
            $start = $now;
        }

        $end = $start->modify('+' . ($weeks * 7) . ' days');

        $result = $this->userManager->updateUser($userId, [
            'subscription_status' => 'active',
            'subscription_start' => $start->format('c'),
            'subscription_end' => $end->format('c'),
            'subscription_type' => 'weekly',
        ]);

        return $result;
    }

    /**
     * Registra un pago en el historial del usuario.
     * Llama a updateUser que gestiona su propio locking interno.
     *
     * @return array{ok: bool, error?: string}
     */
    public function recordPayment(int $userId, float $amount, string $method = 'card', string $transactionId = ''): array
    {
        $user = $this->userManager->getUser($userId);
        if ($user === null) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }

        $payments = $user['payments'] ?? [];
        if (!is_array($payments)) {
            $payments = [];
        }

        $paymentId = count($payments) + 1;
        $paymentRecord = [
            'id' => $paymentId,
            'amount' => $amount,
            'method' => $method,
            'gateway' => 'paypal',
            'date' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('c'),
            'weeks' => 1,
        ];
        if ($transactionId !== '') {
            $paymentRecord['transaction_id'] = $transactionId;
        }
        $payments[] = $paymentRecord;

        $result = $this->userManager->updateUser($userId, ['payments' => $payments]);

        // Notificar a Telegram
        if ($result['ok']) {
            $this->notifyPaymentTelegram($user, $amount, $method);
            $this->notifyPaymentWhatsApp((int) ($user['id'] ?? 0), $amount);
        }

        return $result;
    }

    /**
     * Envía (best-effort) el WhatsApp de confirmación del pago al usuario.
     * Cualquier fallo se registra y nunca rompe el flujo de pago.
     */
    private function notifyPaymentWhatsApp(int $userId, float $amount): void
    {
        try {
            $config = new Config(dirname(__DIR__, 2));
            $notifier = new \WasapBot\Payment\PaymentConfirmationNotifier($config, $this->userManager);
            $notifier->notify($userId, $amount);
        } catch (\Throwable $e) {
            error_log('[SubscriptionManager] notifyPaymentWhatsApp error: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    //  Helper: set unlimited (for admin panel)
    // ─────────────────────────────────────────────────────────

    /**
     * Marca un usuario como "sin restricciones monetarias" (unlimited).
     *
     * @return array{ok: bool, error?: string}
     */
    public function setUnlimited(int $userId): array
    {
        return $this->userManager->updateUser($userId, [
            'subscription_status' => 'unlimited',
            'trial_start' => null,
            'trial_end' => null,
            'subscription_start' => null,
            'subscription_end' => null,
            'subscription_type' => null,
        ]);
    }

    /**
     * Marca un usuario como expirado (admin override).
     *
     * @return array{ok: bool, error?: string}
     */
    public function setExpired(int $userId): array
    {
        return $this->userManager->updateUser($userId, [
            'subscription_status' => 'expired',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Internal helpers
    // ─────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $user
     *
     * @return array{status: string, currentDay: int, totalDays: int, daysLeft: int, isExpired: bool, periodLabel: string, canUseBot: bool, subscriptionEnd: string|null}
     */
    private function computeTrialStatus(array $user, \DateTimeImmutable $now): array
    {
        $trialEnd = (string) ($user['trial_end'] ?? '');
        $trialStart = (string) ($user['trial_start'] ?? '');

        if ($trialEnd === '') {
            return $this->defaultStatus('trial');
        }

        try {
            $endDate = new \DateTimeImmutable($trialEnd);
        } catch (\Exception) {
            return $this->defaultStatus('trial');
        }

        $startDate = null;
        if ($trialStart !== '') {
            try {
                $startDate = new \DateTimeImmutable($trialStart);
            } catch (\Exception) {
                // ignore
            }
        }

        $diff = $now->diff($endDate);
        $daysLeft = (int) $diff->format('%r%a');

        // Current day of trial (1-indexed)
        $currentDay = 0;
        if ($startDate !== null) {
            $dayDiff = $startDate->diff($now);
            $currentDay = min(max((int) $dayDiff->format('%a') + 1, 1), 10);
        } else {
            // Fallback: calculate from days remaining
            $currentDay = max(10 - $daysLeft, 1);
        }

        if ($daysLeft < 0) {
            // Trial expired
            return [
                'status' => 'expired',
                'currentDay' => 10,
                'totalDays' => 10,
                'daysLeft' => 0,
                'isExpired' => true,
                'periodLabel' => 'Prueba gratuita (expirada)',
                'canUseBot' => false,
                'subscriptionEnd' => $trialEnd,
            ];
        }

        return [
            'status' => 'trial',
            'currentDay' => min($currentDay, 10),
            'totalDays' => 10,
            'daysLeft' => max($daysLeft, 0),
            'isExpired' => false,
            'periodLabel' => 'Prueba gratuita',
            'canUseBot' => true,
            'subscriptionEnd' => $trialEnd,
        ];
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array{status: string, currentDay: int, totalDays: int, daysLeft: int, isExpired: bool, periodLabel: string, canUseBot: bool, subscriptionEnd: string|null}
     */
    private function computeActiveStatus(array $user, \DateTimeImmutable $now): array
    {
        $subEnd = (string) ($user['subscription_end'] ?? '');
        $subStart = (string) ($user['subscription_start'] ?? '');

        if ($subEnd === '') {
            return $this->defaultStatus('expired');
        }

        try {
            $endDate = new \DateTimeImmutable($subEnd);
        } catch (\Exception) {
            return $this->defaultStatus('expired');
        }

        $startDate = null;
        if ($subStart !== '') {
            try {
                $startDate = new \DateTimeImmutable($subStart);
            } catch (\Exception) {
                // ignore
            }
        }

        $diff = $now->diff($endDate);
        $daysLeft = (int) $diff->format('%r%a');

        // Total days in this paid period (for weekly, it's 7 days per week)
        $totalDays = 7;
        if ($startDate !== null) {
            $periodDiff = $startDate->diff($endDate);
            $totalDays = max((int) $periodDiff->format('%a'), 7);
        }

        // Current day of paid period
        $currentDay = 0;
        if ($startDate !== null) {
            $dayDiff = $startDate->diff($now);
            $currentDay = min(max((int) $dayDiff->format('%a') + 1, 1), $totalDays);
            // Defense: if $startDate is in the future (shouldn't happen after fix, but belt-and-suspenders)
            if ($now < $startDate) {
                $currentDay = 1;
            }
        } else {
            $currentDay = max($totalDays - $daysLeft, 1);
        }

        if ($daysLeft < 0) {
            // Subscription expired
            return [
                'status' => 'expired',
                'currentDay' => $totalDays,
                'totalDays' => $totalDays,
                'daysLeft' => 0,
                'isExpired' => true,
                'periodLabel' => 'Plan semanal (expirado)',
                'canUseBot' => false,
                'subscriptionEnd' => $subEnd,
            ];
        }

        return [
            'status' => 'active',
            'currentDay' => min($currentDay, $totalDays),
            'totalDays' => $totalDays,
            'daysLeft' => $daysLeft,
            'isExpired' => false,
            'periodLabel' => 'Plan semanal',
            'canUseBot' => true,
            'subscriptionEnd' => $subEnd,
        ];
    }

    /**
     * @return array{status: string, currentDay: int, totalDays: int, daysLeft: int, isExpired: bool, periodLabel: string, canUseBot: bool, subscriptionEnd: string|null}
     */
    private function defaultStatus(string $status): array
    {
        $expired = ($status === 'expired');
        return [
            'status' => $status,
            'currentDay' => 0,
            'totalDays' => 0,
            'daysLeft' => 0,
            'isExpired' => $expired,
            'periodLabel' => $expired ? 'Acceso expirado' : 'Sin definir',
            'canUseBot' => !$expired,
            'subscriptionEnd' => null,
        ];
    }

    /**
     * Notifica un nuevo pago al admin vía Telegram.
     *
     * @param array<string, mixed> $user
     */
    private function notifyPaymentTelegram(array $user, float $amount, string $method): void
    {
        try {
            $token = '7455622229:AAG7qFKsNS52Xn7WkWdxgshqriTZCVQedNE';
            $chatId = '6755848011';
            $name = $user['name'] ?? $user['username'] ?? '?';
            $msg  = "NUEVO PAGO bot-casa\n";
            $msg .= "Usuario: " . $name . " (" . ($user['username'] ?? '?') . ")\n";
            $msg .= "Importe: " . number_format($amount, 2) . " EUR\n";
            $msg .= "Metodo: " . $method . "\n";
            $msg .= "Fecha: " . date('c');
            $url = 'https://api.telegram.org/bot' . $token . '/sendMessage?chat_id=' . $chatId . '&text=' . urlencode($msg);
            @file_get_contents($url);
        } catch (\Throwable) {
            // Silencioso: no interrumpir el flujo de pago
        }
    }
}
