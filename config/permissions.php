<?php

/*
|--------------------------------------------------------------------------
| Permissions, Features and Plans registry
|--------------------------------------------------------------------------
|
| Single source of truth for the RBAC/feature system. Every permission,
| feature and plan the application knows about is declared here and materialised
| into the database by the seeders (`php artisan db:seed`) or, after launch, by
| `php artisan features:sync`.
|
| Permission keys use a namespaced convention:
|
|   platform.<resource>.<action>  -> SaaS-owner (super admin) panel
|   store.<resource>.<action>     -> merchant/tenant application
|
| The namespace keeps tenant permissions strictly separated from SaaS-owner
| permissions, so a store role can never grant platform access and vice versa.
| The context is stored on the row so `Permission::superAdmin()` / store
| lookups are cheap index queries, not string gymnastics.
|
| Adding a feature after launch:
|   1. Add its resource/actions under the right context below.
|   2. Add a feature entry under 'features'.
|   3. Link the feature to a plan under 'plans'.
|   4. Run `php artisan features:sync`.
|
*/

return [

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Seeded accounts
    |--------------------------------------------------------------------------
    |
    | Credentials used by UsersTableSeeder. Override via environment in
    | production; never ship default credentials there.
    |
    */
    'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'superadmin@gmail.com'),
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    'demo_user_email' => env('DEMO_USER_EMAIL', 'user@example.com'),
    'demo_user_password' => env('DEMO_USER_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Permission contexts
    |--------------------------------------------------------------------------
    |
    | Each context declares resources and their CRUD/action verbs. Permission
    | keys are auto-generated as "<context>.<resource>.<action>".
    |
    */
    'contexts' => [

        'platform' => [
            'resources' => [
                'users' => ['view', 'create', 'update', 'delete'],
                'roles' => ['view', 'create', 'update', 'delete'],
                'permissions' => ['view', 'create', 'update', 'delete'],
                'plans' => ['view', 'create', 'update', 'delete'],
                'features' => ['view', 'create', 'update', 'delete'],
                'subscriptions' => ['view', 'manage', 'cancel', 'resume'],
                'stores' => ['view', 'manage', 'delete'],
                'system' => ['view', 'manage'],
            ],
            'extra' => [
                'platform.roles.assign' => 'Assign Roles',
                'platform.roles.revoke' => 'Revoke Roles',
                'platform.permissions.assign' => 'Assign Permissions',
                'platform.activity-logs.view' => 'View Activity Logs',
                'platform.system.health' => 'View System Health',
            ],
        ],

        'store' => [
            'resources' => [
                'settings' => ['view', 'manage'],
                'products' => ['view', 'create', 'update', 'delete', 'manage'],
                'orders' => ['view', 'create', 'update', 'delete', 'manage', 'export'],
                'customers' => ['view', 'create', 'update', 'delete', 'manage'],
                'invoices' => ['view', 'create', 'update', 'delete', 'manage', 'send'],
                'staff' => ['view', 'manage', 'assign', 'remove'],
                'reports' => ['view', 'export'],
            ],
            'extra' => [
                'store.permissions.assign' => 'Assign Store Permissions',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Built-in roles and the permissions each one is granted. The 'permissions'
    | value is either the special token 'platform' (every platform permission),
    | the token 'store' (every store permission), or an explicit list of
    | permission keys.
    |
    */
    'roles' => [
        'super-admin' => [
            'name' => 'Super Admin',
            'context' => 'platform',
            'permissions' => 'platform',
        ],
        'user' => [
            'name' => 'User',
            'context' => 'platform',
            'permissions' => [],
        ],
        'owner' => [
            'name' => 'Store Owner',
            'context' => 'store',
            'permissions' => 'store',
        ],
        'manager' => [
            'name' => 'Store Manager',
            'context' => 'store',
            'permissions' => [
                'store.settings.view',
                'store.products.view',
                'store.products.create',
                'store.products.update',
                'store.orders.view',
                'store.orders.create',
                'store.orders.update',
                'store.customers.view',
                'store.customers.create',
                'store.customers.update',
                'store.invoices.view',
                'store.invoices.create',
                'store.invoices.send',
                'store.staff.view',
                'store.reports.view',
                'store.reports.export',
            ],
        ],
        'staff' => [
            'name' => 'Store Staff',
            'context' => 'store',
            'permissions' => [
                'store.settings.view',
                'store.products.view',
                'store.orders.view',
                'store.customers.view',
                'store.invoices.view',
                'store.reports.view',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Features are plan-level capabilities. Each feature may map to a single
    | permission key that gates it inside the app ("permission_key"), or be a
    | pure capability flag (permission_key => null, e.g. multi-store which is
    | enforced through plan.max_stores).
    |
    */
    'features' => [
        [
            'key' => 'store-management',
            'name' => 'Store Management',
            'description' => 'Create and manage your store workspace.',
            'permission_key' => 'store.settings.manage',
            'context' => 'store',
        ],
        [
            'key' => 'product-management',
            'name' => 'Product Management',
            'description' => 'Create, edit and delete products.',
            'permission_key' => 'store.products.manage',
            'context' => 'store',
        ],
        [
            'key' => 'order-management',
            'name' => 'Order Management',
            'description' => 'Process and export orders.',
            'permission_key' => 'store.orders.manage',
            'context' => 'store',
        ],
        [
            'key' => 'customer-management',
            'name' => 'Customer Management',
            'description' => 'Maintain a customer directory.',
            'permission_key' => 'store.customers.manage',
            'context' => 'store',
        ],
        [
            'key' => 'invoice-management',
            'name' => 'Invoice Management',
            'description' => 'Create and send invoices.',
            'permission_key' => 'store.invoices.manage',
            'context' => 'store',
        ],
        [
            'key' => 'staff-management',
            'name' => 'Staff Management',
            'description' => 'Invite staff and manage store roles.',
            'permission_key' => 'store.staff.manage',
            'context' => 'store',
        ],
        [
            'key' => 'reports',
            'name' => 'Reports & Analytics',
            'description' => 'Access store reports and exports.',
            'permission_key' => 'store.reports.export',
            'context' => 'store',
        ],
        [
            'key' => 'multi-store',
            'name' => 'Multiple Stores',
            'description' => 'Open more than one store.',
            'permission_key' => null,
            'context' => 'store',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Declared plans and the features they grant. Feature value = whether the
    | feature is available during the trial period (is_trial_allowed).
    |
    */
    'plans' => [
        'default' => [
            'name' => 'Default',
            'slug' => 'default',
            'description' => 'The default plan every new account starts on. Includes a free trial.',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_days' => 14,
            'max_stores' => 1,
            'max_staff_per_store' => 2,
            'is_default' => true,
            'features' => [
                'store-management' => true,
                'product-management' => true,
                'customer-management' => true,
            ],
        ],
        'starter' => [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For growing merchants who need orders and a small team.',
            'price' => 29,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_days' => 14,
            'max_stores' => 2,
            'max_staff_per_store' => 5,
            'is_default' => false,
            'features' => [
                'store-management' => true,
                'product-management' => true,
                'order-management' => true,
                'customer-management' => true,
                'invoice-management' => false,
                'staff-management' => true,
                'reports' => false,
                'multi-store' => true,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'The full-featured merchant plan.',
            'price' => 99,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'trial_days' => 14,
            'max_stores' => 10,
            'max_staff_per_store' => 25,
            'is_default' => false,
            'features' => [
                'store-management' => true,
                'product-management' => true,
                'order-management' => true,
                'customer-management' => true,
                'invoice-management' => true,
                'staff-management' => true,
                'reports' => true,
                'multi-store' => true,
            ],
        ],
    ],
];
