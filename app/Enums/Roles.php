<?php

namespace App\Enums;

enum Roles: string
{
    case SUPER_ADMIN = 'super-admin';
    case USER = 'user';

    // Store-scoped roles (assigned with a team_id in model_has_roles)
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case STAFF = 'staff';

    /**
     * Whether the role is a platform (SaaS-owner) role.
     */
    public function isPlatform(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::USER], true);
    }

    /**
     * The permission namespace this role belongs to.
     */
    public function context(): PermissionContext
    {
        return $this->isPlatform() ? PermissionContext::PLATFORM : PermissionContext::STORE;
    }
}
