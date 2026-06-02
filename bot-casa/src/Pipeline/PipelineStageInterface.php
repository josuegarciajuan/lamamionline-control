<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

/**
 * Pipeline stage contract — each stage receives the message context and returns (possibly modified) context.
 * If it returns null, the pipeline stops (message rejected).
 */
interface PipelineStageInterface
{
    /**
     * @param array<string, mixed> $ctx  Message context flowing through pipeline.
     * @return array<string, mixed>|null  Modified context, or null to halt.
     */
    public function process(array $ctx): ?array;

    public function name(): string;
}
