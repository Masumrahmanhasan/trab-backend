<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RoleResource;
use App\Models\Role;
use App\Traits\ApiResponse;

class RoleController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return RoleResource::collection(Role::query()->paginate());
    }

    public function show(Role $role)
    {
        return new RoleResource($role);
    }
}
