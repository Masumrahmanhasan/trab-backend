<?php
return [
    'resources' => [
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'permissions' => ['view', 'create', 'update', 'delete']
    ],

    'extra' => [
        'roles-assign' => 'Assign Role',
        'permissions-assign' => 'Assign Permission',
        'roles-revoke' => 'Revoke Role',
        'permissions-revoke' => 'Revoke Permission',
    ]
];