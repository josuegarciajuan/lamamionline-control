<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Pre-LLM intent router — minimal fast-path.
 *
 * Only routes obvious first-contact greetings to template responses.
 * Everything else (price, location, photos, goodbye, confirm) goes to
 * the LLM which understands context and can personalize responses.
 *
 * This replaces ~60% regex-based routing with LLM semantic understanding.
 */
final class IntentRouter implements PipelineStageInterface
{
    /** @var array<string, list<string>> */
    private array $templates;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->templates = [
            'greeting' => (array) $this->config->get('message_variants.intent_greetings', [
                'hola cari 😊',
                'hola guapo 😘',
                'buenas 😏',
                'dime amor 🔥',
                'hola cielo 😊',
            ]),
        ];
    }

    public function process(array $ctx): ?array
    {
        // Already skipped LLM? Don't re-route
        if (!empty($ctx['__skip_llm'])) {
            return $ctx;
        }

        $messageText = (string) ($ctx['message_text'] ?? '');
        if ($messageText === '') {
            return $ctx;
        }

        $normalized = mb_strtolower(trim($messageText), 'UTF-8');
        $isNewConv  = !empty($ctx['__is_new_conversation']);

        // ── Only fast-path: greeting on NEW conversations ────────────
        if ($isNewConv && $this->isGreeting($normalized)) {
            $variants = $this->templates['greeting'] ?? [];
            if ($variants !== []) {
                $ctx['output_text']   = $variants[array_rand($variants)];
                $ctx['lead_detected'] = false;
                $ctx['photo_action']  = 'none';
                $ctx['__skip_llm']    = true;
                $ctx['__intent']      = 'greeting';

                if ($this->logger !== null) {
                    $this->logger->info('IntentRouter: greeting fast-path, skipping LLM', [
                        'phone' => $ctx['from_phone'] ?? '?',
                    ]);
                }
                return $ctx;
            }
        }

        // ── Everything else → LLM handles it ─────────────────────────
        return $ctx;
    }

    public function name(): string
    {
        return 'IntentRouter';
    }

    // ─────────────────────────────────────────────────────────────────

    private function isGreeting(string $t): bool
    {
        return (bool) preg_match(
            '/^(hola|holis|buenas?|hey|saludos?|ola|ola\s+k\s+ase|alo|aló|buen\s*d[ií]a|buenas\s*(tardes|noches)?)[\s!😊😘😏🔥]*$/iu',
            $t
        );
    }
}
