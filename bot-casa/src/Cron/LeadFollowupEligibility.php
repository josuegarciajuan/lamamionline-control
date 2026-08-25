<?php

declare(strict_types=1);

namespace WasapBot\Cron;

final class LeadFollowupEligibility
{
    /**
     * Follow-up is reserved for leads confirmed as having arrived.
     *
     * @param array<string, mixed> $lead
     */
    public static function shouldInclude(array $lead): bool
    {
        return ($lead['arrived'] ?? false) === true;
    }
}
