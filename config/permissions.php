<?php

return [
    // Super admin permissions - platform-wide management
    'super_admin' => [
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
            'roles-assign' => 'Assign Role',
            'permissions-assign' => 'Assign Permission',
            'roles-revoke' => 'Revoke Role',
            'permissions-revoke' => 'Revoke Permission',
            'subscription-manage' => 'Manage Subscription',
            'system-health' => 'View System Health',
            'activity-logs' => 'View Activity Logs',
        ],
    ],

    // Store permissions - based on features and plans
    'store' => [
        'resources' => [
            'products' => ['view', 'create', 'update', 'delete'],
            'orders' => ['view', 'create', 'update', 'delete'],
            'customers' => ['view', 'create', 'update', 'delete'],
            'inventory' => ['view', 'manage'],
            'reports' => ['view'],
            'staff' => ['view', 'manage'],
            'settings' => ['view', 'update'],
        ],
        'extra' => [
            'staff-assign' => 'Assign Staff',
            'staff-remove' => 'Remove Staff',
            'permissions-assign' => 'Assign Permissions',
            'export-data' => 'Export Data',
            'import-data' => 'Import Data',
        ],
    ],
];
