<?php

namespace App\Enums;

enum BillingCycle: int
{
    case MONTHLY = 1;
    case YEARLY = 2;
    case HALF_YEARLY = 3;
    case QUARTERLY = 4;
    case WEEKLY = 5;
}
