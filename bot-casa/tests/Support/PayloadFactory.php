<?php

declare(strict_types=1);

namespace WasapBot\Tests\Support;

/**
 * PayloadFactory — builds realistic WAHA webhook payloads for testing.
 *
 * Reproduces the various WAHA payload shapes (text, audio, image, GOWS/LID,
 * opening bursts, etc.) that the pipeline (especially MessageExtractor and
 * Coalescer) must handle.
 */
final class PayloadFactory
{
    /**
     * Standard text message webhook.
     *
     * @param string $text        Message body text.
     * @param string $fromPhone   Sender phone (digits only, e.g. "34600123456").
     * @param string $toLineLast9 Last 9 digits of the receiving WAHA line.
     * @param string $wamid       WAHA message ID (optional).
     */
    public static function text(string $text, string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_TEST_001'): array
    {
        return [
            'event' => 'message',
            'me'    => ['id' => $toLineLast9 . '@c.us'],
            'payload' => [
                'id'        => $wamid,
                'from'      => $fromPhone . '@c.us',
                'to'        => $toLineLast9 . '@c.us',
                'body'      => $text,
                'type'      => 'text',
                'timestamp' => time(),
            ],
        ];
    }

    /**
     * Text message on a GOWS engine where `from` is a LID.
     *
     * @param string $text        Message body text.
     * @param string $realPhone   Real E.164 phone (in SenderAlt, e.g. "34654464023").
     * @param string $lid         The LID used as `from` (e.g. "277476546711679@lid").
     * @param string $toLineLast9 Receiver line last9.
     * @param string $wamid       WAHA message ID.
     */
    public static function lid(string $text, string $realPhone, string $lid, string $toLineLast9 = '000000000', string $wamid = 'WAMID_LID_001'): array
    {
        return [
            'event' => 'message',
            'me'    => ['id' => $toLineLast9 . '@c.us'],
            'payload' => [
                'id'        => $wamid,
                'from'      => $lid,
                'to'        => $toLineLast9 . '@c.us',
                'body'      => $text,
                'type'      => 'text',
                'timestamp' => time(),
                '_data' => [
                    'Info' => [
                        'SenderAlt' => $realPhone . '@s.whatsapp.net',
                        'MediaType' => 'text',
                    ],
                ],
            ],
        ];
    }

    /**
     * Audio message webhook.
     *
     * @param string $fromPhone   Sender phone.
     * @param string $toLineLast9 Receiver line last9.
     * @param string $wamid       WAHA message ID.
     */
    public static function audio(string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_AUDIO_001'): array
    {
        return [
            'event' => 'message',
            'me'    => ['id' => $toLineLast9 . '@c.us'],
            'payload' => [
                'id'        => $wamid,
                'from'      => $fromPhone . '@c.us',
                'to'        => $toLineLast9 . '@c.us',
                'type'      => 'audio',
                'timestamp' => time(),
                'media'     => ['mimetype' => 'audio/ogg'],
                'ptt'       => true,
                'hasMedia'  => true,
            ],
        ];
    }

    /**
     * Image-only message (no caption).
     *
     * @param string $fromPhone   Sender phone.
     * @param string $toLineLast9 Receiver line last9.
     * @param string $wamid       WAHA message ID.
     */
    public static function image(string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_IMG_001'): array
    {
        return [
            'event' => 'message',
            'me'    => ['id' => $toLineLast9 . '@c.us'],
            'payload' => [
                'id'        => $wamid,
                'from'      => $fromPhone . '@c.us',
                'to'        => $toLineLast9 . '@c.us',
                'type'      => 'image',
                'timestamp' => time(),
                '_data' => [
                    'Info'      => ['MediaType' => 'image'],
                    'Message'   => ['imageMessage' => ['url' => 'https://example.com/img.jpg']],
                ],
            ],
        ];
    }

    /**
     * Location message (WhatsApp shared location).
     *
     * @param string $fromPhone   Sender phone.
     * @param string $toLineLast9 Receiver line last9.
     * @param string $wamid       WAHA message ID.
     */
    public static function location(string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_LOC_001'): array
    {
        return [
            'event' => 'message',
            'me'    => ['id' => $toLineLast9 . '@c.us'],
            'payload' => [
                'id'        => $wamid,
                'from'      => $fromPhone . '@c.us',
                'to'        => $toLineLast9 . '@c.us',
                'type'      => 'location',
                'timestamp' => time(),
                'location'  => [
                    'latitude'  => 39.8894,
                    'longitude' => -0.1005,
                    'name'      => 'Burriana',
                    'address'   => 'Burriana, Castellón',
                ],
            ],
        ];
    }

    /**
     * Opening burst: web portal auto-message + client greeting.
     * E.g., "He visto tu anuncio en milanuncios.com hola"
     */
    public static function openingBurst(string $combinedText = 'he visto tu anuncio en milanuncios.com hola', string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_BURST_001'): array
    {
        return self::text($combinedText, $fromPhone, $toLineLast9, $wamid);
    }

    /**
     * Greeting-only message (first contact).
     */
    public static function greeting(string $text = 'hola', string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_GREET_001'): array
    {
        return self::text($text, $fromPhone, $toLineLast9, $wamid);
    }

    /**
     * A question about prices.
     */
    public static function priceQuestion(string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_PRICE_001'): array
    {
        return self::text('cuanto cobras?', $fromPhone, $toLineLast9, $wamid);
    }

    /**
     * ETA / lead message.
     */
    public static function eta(string $text = 'voy en 20 min', string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_ETA_001'): array
    {
        return self::text($text, $fromPhone, $toLineLast9, $wamid);
    }

    /**
     * A message that arrived during an inflight lock (anti-metralleta).
     * Simulates a second message arriving while the first is processing.
     */
    public static function inflightArrival(string $text, string $fromPhone = '34600123456', string $toLineLast9 = '000000000', string $wamid = 'WAMID_INFLIGHT_002'): array
    {
        return self::text($text, $fromPhone, $toLineLast9, $wamid);
    }

    /**
     * Build a webhook body in the shape that webhook.php expects (nested under 'payload').
     */
    public static function asWebhookBody(array $payload): array
    {
        return ['payload' => $payload['payload'] ?? $payload];
    }
}
