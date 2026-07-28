<?php

namespace App\Enums;

enum SubscriptionStatus: int
{
    case ACTIVE = 1;
    case INACTIVE = 0;
    case CANCELED = 2;
    case PENDING = 3;
    case TRIAL = 4;
}
