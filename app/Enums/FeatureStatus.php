<?php

namespace App\Enums;

enum FeatureStatus: int
{
    case ACTIVE = 1;
    case INACTIVE = 0;
    case DRAFT = 2;

}
