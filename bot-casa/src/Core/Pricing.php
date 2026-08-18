<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Pricing — cálculo de precios del plan semanal de CasaWasap.
 *
 * Los precios base se leen de config (pricing.weekly_price, pricing.extra_line_price)
 * y admiten descuentos por usuario vía pricing.user_override (mapa username → importe).
 * Así, p. ej., un usuario de pruebas puede pagar 1€/semana en lugar de 100€.
 */
final class Pricing
{
    /**
     * Precio base del plan semanal (1 línea incluida), con descuento por usuario.
     */
    public static function weeklyBase(int $userId): float
    {
        $config = new Config(dirname(__DIR__, 2));
        $default = (float) ($config->get('pricing.weekly_price') ?? 100);
        $overrides = $config->get('pricing.user_override', []);
        if (!is_array($overrides)) {
            $overrides = [];
        }

        return self::resolveOverride($overrides, self::usernameOf($userId), $userId, $default);
    }

    /**
     * Precio por línea extra semanal.
     */
    public static function extraLine(): float
    {
        $config = new Config(dirname(__DIR__, 2));
        return (float) ($config->get('pricing.extra_line_price') ?? 25);
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

    /**
     * Aplica el descuento por usuario: primero por username, luego por id numérico.
     *
     * Para el username se prueba primero el valor tal cual y después sin el prefijo
     * de país "34" (formato nacional): así la clave de override "654464023" hace match
     * con un usuario cuyo username sea "34654464023".
     *
     * @param array<mixed> $overrides Mapa username|id → importe en euros.
     */
    public static function resolveOverride(array $overrides, string $username, int $userId, float $default): float
    {
        if ($username !== '') {
            if (isset($overrides[$username])) {
                return (float) $overrides[$username];
            }
            $national = preg_replace('/^34/', '', $username, 1);
            if (is_string($national) && $national !== '' && isset($overrides[$national])) {
                return (float) $overrides[$national];
            }
        }
        if (isset($overrides[(string) $userId])) {
            return (float) $overrides[(string) $userId];
        }

        return $default;
    }

    private static function usernameOf(int $userId): string
    {
        $um = new UserManager(dirname(__DIR__, 2));
        $user = $um->getUser($userId);

        return (string) ($user['username'] ?? '');
    }
}
