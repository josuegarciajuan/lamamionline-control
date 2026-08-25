<?php

declare(strict_types=1);

/**
 * Public CasaWasap product pricing. This file must never contain credentials.
 * It is shared by bot-casa billing and CRM/commercial marketing.
 *
 * @return array{currency: string, weekly_price: float, extra_line_price: float, period_days: int}
 */
return [
    'currency' => 'EUR',
    'weekly_price' => 50.0,
    'extra_line_price' => 10.0,
    'period_days' => 7,
];
