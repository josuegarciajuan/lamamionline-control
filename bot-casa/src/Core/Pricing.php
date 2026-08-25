<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Pricing — cálculo de precios del plan semanal de CasaWasap.
 *
 * Los precios públicos se leen del único fichero no-secreto compartido con el CRM.
 */
final class Pricing
{
    /**
     * Precio base del plan semanal (1 línea incluida), con descuento por usuario.
     */
    public static function weeklyBase(int $userId): float
    {
        return (float) self::sharedConfig()['weekly_price'];
    }

    /**
     * Precio por línea extra semanal.
     */
    public static function extraLine(): float
    {
        return (float) self::sharedConfig()['extra_line_price'];
    }

    /**
     * Precio semanal total: 1 línea incluida + líneas extra.
     *
     * @param int $lineCount Nº total de líneas del usuario (0 y 1 cuentan igual: solo la base).
     */
    public static function weeklyTotal(int $userId, int $lineCount): float
    {
        return self::weeklyBase($userId) + (max($lineCount - 1, 0) * self::extraLine());
    }

    /**
     * Initial extra-line charge, based on whole calendar days remaining.
     */
    public static function extraLineInitialPrice(int $userId, ?\DateTimeImmutable $now = null): float
    {
        $user = (new UserManager(dirname(__DIR__, 2)))->getUser($userId);
        $end = is_array($user) ? (string) ($user['subscription_end'] ?? '') : '';
        if ($end === '') return self::extraLine();

        $days = self::wholeDaysRemaining($now ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')), $end);
        return self::proratedExtraLine($days);
    }

    public static function proratedExtraLine(int $daysRemaining): float
    {
        $days = max(0, min($daysRemaining, (int) self::sharedConfig()['period_days']));
        return round(self::extraLine() * $days / (int) self::sharedConfig()['period_days'], 2);
    }

    public static function wholeDaysRemaining(\DateTimeImmutable $now, string $subscriptionEnd): int
    {
        try {
            $end = new \DateTimeImmutable($subscriptionEnd, $now->getTimezone());
        } catch (\Exception) {
            return 0;
        }

        $today = new \DateTimeImmutable($now->format('Y-m-d'), $now->getTimezone());
        $endDate = new \DateTimeImmutable($end->format('Y-m-d'), $now->getTimezone());
        return max(0, (int) $today->diff($endDate)->format('%r%a'));
    }

    /**
     * Nº de líneas activas del usuario.
     * Lee lines_map.json y, si no hay entradas para el usuario, fallback a users/<id>/lines.json.
     */
    public static function userLineCount(int $userId, string $baseDir): int
    {
        $baseDir = rtrim($baseDir, '/');
        $linesMapFile = $baseDir . '/data/lines_map.json';
        $count = 0;

        if (file_exists($linesMapFile)) {
            $linesMap = @json_decode((string) @file_get_contents($linesMapFile), true);
            if (is_array($linesMap)) {
                foreach ($linesMap as $uid) {
                    if ((int) $uid === $userId) {
                        $count++;
                    }
                }
            }
        }

        if ($count <= 0) {
            $userLinesFile = $baseDir . '/data/users/' . $userId . '/lines.json';
            if (file_exists($userLinesFile)) {
                $userLines = @json_decode((string) @file_get_contents($userLinesFile), true);
                if (is_array($userLines)) {
                    $count = count($userLines);
                }
            }
        }

        return $count;
    }

    /** @return array{currency: string, weekly_price: float, extra_line_price: float, period_days: int} */
    private static function sharedConfig(): array
    {
        static $pricing;
        if (!is_array($pricing)) {
            $pricing = require dirname(__DIR__, 3) . '/config/casawasap_pricing.php';
        }
        return $pricing;
    }
}
