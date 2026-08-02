<?php

return [
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
];
