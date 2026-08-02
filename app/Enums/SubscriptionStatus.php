<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case TRIALING = 'trialing';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
