<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\FromMeResolver;

/**
 * Unit tests for FromMeResolver — the native-WhatsApp (fromMe) / source /
 * peer-phone resolution extracted from webhook.php.
 */
final class FromMeResolverTest extends TestCase
{
    /**
     * Mirrors webhook.php's variable setup and returns the resolved result.
     *
     * @param array<string, mixed> $webhook  full decoded WAHA webhook payload
     * @param string               $incomingSender pre-resolved incoming sender phone (SenderAlt)
     * @return array{from_me: bool, source: string, sender_phone: string}
     */
    private function resolve(array $webhook, string $incomingSender = ''): array
    {
        $body     = $webhook['payload'] ?? $webhook;
        $payload  = $webhook;
        $dataInfo = $body['_data']['Info'] ?? $payload['_data']['Info'] ?? [];

        return FromMeResolver::resolve(
            is_array($body) ? $body : [],
            $payload,
            is_array($dataInfo) ? $dataInfo : [],
            $incomingSender,
        );
    }

    public function test_outgoing_native_uses_recipient_alt_as_peer_phone(): void
    {
        // Real GOWS "message.any" outgoing shape: Chat/Sender are LIDs,
        // the real customer phone is in RecipientAlt.
        $webhook = [
            'event' => 'message.any',
            'me'    => ['id' => '34654464023@c.us'],
            'payload' => [
                'id'     => 'true_147683171909711@lid_ABC',
                'from'   => '147683171909711@lid',
                'fromMe' => true,
                'source' => 'app',
                'body'   => 'Hola',
                'to'     => null,
                '_data' => [
                    'Info' => [
                        'Chat'         => '147683171909711@lid',
                        'Sender'       => '277476546711679@lid',
                        'IsFromMe'     => true,
                        'SenderAlt'    => '',
                        'RecipientAlt' => '34617505097@s.whatsapp.net',
                    ],
                ],
            ],
        ];

        $r = $this->resolve($webhook, '');

        $this->assertTrue($r['from_me']);
        $this->assertSame('app', $r['source']);
        $this->assertSame('34617505097', $r['sender_phone'], 'RecipientAlt (real phone) must win over Chat (LID)');
    }

    public function test_outgoing_without_recipient_alt_falls_back_to_chat(): void
    {
        $webhook = [
            'payload' => [
                'from'   => '34600123456@c.us',
                'fromMe' => true,
                'source' => 'app',
                'body'   => 'hola',
                'to'     => null,
                '_data' => [
                    'Info' => [
                        'Chat'      => '34600123456@s.whatsapp.net',
                        'IsFromMe'  => true,
                        'SenderAlt' => '',
                    ],
                ],
            ],
        ];

        $r = $this->resolve($webhook, '');

        $this->assertSame('34600123456', $r['sender_phone']);
    }

    public function test_outgoing_without_alt_or_chat_falls_back_to_to(): void
    {
        $webhook = [
            'payload' => [
                'from'   => '34654464023@c.us',
                'fromMe' => true,
                'source' => 'app',
                'body'   => 'hola',
                'to'     => '34600123456@c.us',
            ],
        ];

        $r = $this->resolve($webhook, '');

        $this->assertSame('34600123456', $r['sender_phone']);
    }

    public function test_api_source_is_detected(): void
    {
        $webhook = [
            'payload' => [
                'from'   => '34600123456@c.us',
                'fromMe' => true,
                'source' => 'api',
                'body'   => 'reply from bot',
                'to'     => null,
                '_data' => [
                    'Info' => [
                        'Chat'         => '34600123456@s.whatsapp.net',
                        'IsFromMe'     => true,
                        'RecipientAlt' => '',
                    ],
                ],
            ],
        ];

        $r = $this->resolve($webhook, '');

        $this->assertTrue($r['from_me']);
        $this->assertSame('api', $r['source'], 'source=api must be distinguishable so webhook.php can skip it');
    }

    public function test_incoming_message_keeps_sender_phone_unchanged(): void
    {
        $webhook = [
            'payload' => [
                'from'   => '277476546711679@lid',
                'fromMe' => false,
                'source' => 'app',
                'body'   => 'Holaaa',
                'to'     => null,
                '_data' => [
                    'Info' => [
                        'Chat'      => '277476546711679@lid',
                        'IsFromMe'  => false,
                        'SenderAlt' => '34654464023@s.whatsapp.net',
                    ],
                ],
            ],
        ];

        $r = $this->resolve($webhook, '34654464023');

        $this->assertFalse($r['from_me']);
        $this->assertSame('app', $r['source']);
        $this->assertSame('34654464023', $r['sender_phone'], 'Incoming sender phone must not be overridden');
    }

    public function test_from_me_detected_via_data_info_isfromme(): void
    {
        // Some engines only set _data.Info.IsFromMe, not the top-level fromMe flag.
        $webhook = [
            'payload' => [
                'from'   => '277476546711679@lid',
                'body'   => 'Hola',
                'to'     => null,
                '_data' => [
                    'Info' => [
                        'Chat'         => '147683171909711@lid',
                        'IsFromMe'     => true,
                        'SenderAlt'    => '',
                        'RecipientAlt' => '34617505097@s.whatsapp.net',
                    ],
                ],
            ],
        ];

        $r = $this->resolve($webhook, '');

        $this->assertTrue($r['from_me']);
        $this->assertSame('34617505097', $r['sender_phone']);
    }
}
