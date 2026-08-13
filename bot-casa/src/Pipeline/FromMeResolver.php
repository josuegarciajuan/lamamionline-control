<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

/**
 * FromMeResolver — resolves fromMe / source / peer phone from a WAHA payload.
 *
 * Extracted from webhook.php so the native-WhatsApp (fromMe) resolution logic
 * is unit-testable. Behaviour mirrors exactly what webhook.php did inline.
 *
 * WAHA 'source' semantics:
 *   - 'app' → message created on the device/native WhatsApp (a human replied).
 *   - 'api' → message sent through the WAHA API (the bot itself) — these are
 *     already persisted by the pipeline and must be skipped by the caller.
 *
 * GOWS engine specifics:
 *   - Incoming:  real phone is in _data.Info.SenderAlt.
 *   - Outgoing:  real phone is in _data.Info.RecipientAlt (Chat is a LID).
 */
final class FromMeResolver
{
    /**
     * @param array<string, mixed> $body        payload['payload'] ?? payload
     * @param array<string, mixed> $payload     full decoded webhook payload
     * @param array<string, mixed> $dataInfo    _data.Info (may be empty)
     * @param string               $senderPhone sender phone already resolved for the incoming case
     *
     * @return array{from_me: bool, source: string, sender_phone: string}
     */
    public static function resolve(array $body, array $payload, array $dataInfo, string $senderPhone): array
    {
        $fromMe = (bool) ($body['fromMe'] ?? $payload['fromMe'] ?? !empty($dataInfo['IsFromMe']));
        $source = (string) ($body['source'] ?? $payload['source'] ?? '');

        if ($fromMe) {
            $rawTo    = $body['to'] ?? $payload['to'] ?? '';
            $destAlt  = $dataInfo['RecipientAlt'] ?? '';
            $destChat = $dataInfo['Chat'] ?? '';

            if (is_string($destAlt) && $destAlt !== '') {
                $senderPhone = (string) preg_replace('/[^0-9]/', '', $destAlt);
            } elseif (is_string($destChat) && $destChat !== '') {
                $senderPhone = (string) preg_replace('/[^0-9]/', '', $destChat);
            } elseif (is_string($rawTo) && $rawTo !== '') {
                $senderPhone = (string) preg_replace('/[^0-9]/', '', $rawTo);
            }
        }

        return [
            'from_me'      => $fromMe,
            'source'       => $source,
            'sender_phone' => $senderPhone,
        ];
    }
}
