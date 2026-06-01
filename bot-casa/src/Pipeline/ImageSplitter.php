<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * Separates image URLs from text for individual sending.
 *
 * If the output_text contains image URLs:
 *   - First message: text only (no image URLs)
 *   - Subsequent messages: one URL per message (solo-link)
 * Each message carries __is_first, __split_index, __presend_sleep_sec.
 *
 * If no image URLs → single message.
 *
 * Pattern: node "Split Outgoing (images as solo-link msgs)" from bot.json
 */
final class ImageSplitter implements PipelineStageInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function process(array $ctx): ?array
    {
        $outputText = $ctx['output_text'] ?? '';
        if (!is_string($outputText) || $outputText === '') {
            $ctx['splitted_messages'] = [];
            return $ctx;
        }

        // --- Extract all URLs ---
        if (preg_match_all('#https?://[^\s<>"\')\]}\p{C}]+#u', $outputText, $urlMatches) === false
            || empty($urlMatches[0])) {
            // No URLs at all → single message
            $ctx['splitted_messages'] = [
                $this->buildMessage($outputText, 0, true),
            ];
            return $ctx;
        }

        $allUrls = $urlMatches[0];

        // Separate image URLs from non-image/maps URLs
        $imageUrls  = [];
        $otherUrls  = [];

        foreach ($allUrls as $url) {
            if ($this->isMapsUrl($url)) {
                $otherUrls[] = $url;
            } elseif ($this->isLikelyImageUrl($url)) {
                $imageUrls[] = $url;
            } else {
                $otherUrls[] = $url;
            }
        }

        if ($imageUrls === []) {
            // No image URLs → single message with all content
            $ctx['splitted_messages'] = [
                $this->buildMessage($outputText, 0, true),
            ];
            return $ctx;
        }

        // --- Build text-only first message ---
        // Remove image URLs from text (keep other URLs like maps)
        $textOnly = $outputText;
        foreach ($imageUrls as $imgUrl) {
            $textOnly = str_replace($imgUrl, '', $textOnly);
        }
        // Clean up: remove empty lines and extra whitespace
        $textOnly = trim((string) preg_replace('/\n{3,}/', "\n\n", $textOnly));
        $textOnly = trim((string) preg_replace('/^\s*\n/', '', $textOnly));
        $textOnly = rtrim($textOnly);

        $messages = [];

        // First message: text only
        if ($textOnly !== '') {
            $messages[] = $this->buildMessage($textOnly, 0, true);
        }

        // Build image messages — preserving the image URL on its own line
        $presendSleep = (int) $this->config->get('human_delays.presend_sleep_sec', 15);
        $splitIndex   = $textOnly !== '' ? 1 : 0;

        foreach ($imageUrls as $imgUrl) {
            $caption = ''; // solo-link: just the URL
            $imgMsg  = $this->buildMessage($imgUrl, $splitIndex, $splitIndex === 0, $presendSleep);
            $messages[] = $imgMsg;
            $splitIndex++;
        }

        $ctx['splitted_messages'] = $messages;

        return $ctx;
    }

    public function name(): string
    {
        return 'ImageSplitter';
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Build a message entry for the splitted_messages array.
     *
     * @return array<string, mixed>
     */
    private function buildMessage(
        string $text,
        int $index,
        bool $isFirst,
        ?int $presendSleepSec = null
    ): array {
        $msg = [
            '__split_index' => $index,
            '__is_first'    => $isFirst,
        ];
        // Text goes as the content
        $msg['text'] = $text;

        // Presend sleep only for non-first messages
        if ($presendSleepSec !== null && !$isFirst) {
            $msg['__presend_sleep_sec'] = $presendSleepSec;
        }

        return $msg;
    }

    private function isMapsUrl(string $url): bool
    {
        return str_contains($url, 'maps.google')
            || str_contains($url, 'maps.app.goo.gl')
            || str_contains($url, 'goo.gl/maps');
    }

    private function isLikelyImageUrl(string $url): bool
    {
        // Explicit image extensions (.jpg, .jpeg, .png, .webp, .gif, .bmp, .svg)
        if (preg_match('/\.(?:jpg|jpeg|png|webp|gif|bmp|svg)(?:\?|#|$)/i', $url)) {
            return true;
        }
        // Query param with image format: ?format=jpg, &type=png, ?fm=webp, etc.
        if (preg_match('/[?&](?:format|type|fm|output)=(?:jpg|jpeg|png|webp|gif|bmp|svg)/i', $url)) {
            return true;
        }
        // Common image/keyword path segments
        if (preg_match('#/(?:photo|image|img|pic|fotos?|girls?|photos?|images?|uploads?)/#i', $url)) {
            return true;
        }
        // Image-related query params (width, height, size — typical CDN resize params)
        if (preg_match('/[?&](?:image=|photo=|img=|pic=|w=|width=|h=|height=|size=)/i', $url)) {
            return true;
        }
        // Common image CDN / cloud storage hosts
        if (preg_match('#//(?:[^/]*\.)?(?:imgur\.com|cloudinary\.com|cloudfront\.net|amazonaws\.com|digitaloceanspaces\.com|imagekit\.io|supabase\.co)#i', $url)) {
            return true;
        }
        // Direct image URLs from common CDNs/hosting
        if (preg_match('#//(?:i\.)?imgur\.com#', $url)) {
            return true;
        }
        // OG-image shortlink hosts (compartir.site) — WhatsApp link preview
        if (preg_match('#//(?:[^/]*\.)?compartir\.site/#i', $url)) {
            return true;
        }
        return false;
    }
}
