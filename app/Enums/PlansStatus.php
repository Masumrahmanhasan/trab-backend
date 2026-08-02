<?php

namespace App\Enums;

enum PlansStatus: int
{
    case ACTIVE = 1;
    case INACTIVE = 0;
    case DRAFT = 2;

    public static function defaultPlanKey(): string
    {
        return 'default';
    }

}
