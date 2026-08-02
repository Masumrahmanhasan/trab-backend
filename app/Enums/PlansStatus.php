<?php

namespace App\Enums;

enum PlansStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DRAFT = 'draft';

    public static function defaultPlanKey(): string
    {
        return 'default';
    }
}
