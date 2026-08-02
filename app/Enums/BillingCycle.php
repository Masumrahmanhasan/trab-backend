<?php

namespace App\Enums;

enum BillingCycle: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half_yearly';
    case YEARLY = 'yearly';

    /**
     * Number of months a full billing period covers.
     */
    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::HALF_YEARLY => 6,
            self::YEARLY => 12,
        };
    }
}
