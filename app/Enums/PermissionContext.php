<?php

namespace App\Enums;

/**
 * Permission/role namespaces.
 *
 * - platform: SaaS-owner panel. Keys look like `platform.users.view`.
 * - store:    merchant (tenant) application. Keys look like `store.orders.view`.
 *
 * Keeping the two namespaces distinct is what prevents a tenant role from ever
 * being granted SaaS-owner panel access (and vice versa).
 */
enum PermissionContext: string
{
    case PLATFORM = 'platform';
    case STORE = 'store';
}
