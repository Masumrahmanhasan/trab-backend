<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PermissionResource;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return PermissionResource::collection(
            Permission::query()->paginate()
        );
    }

    public function show(Permission $permission)
    {
        return new PermissionResource($permission);
    }
}
