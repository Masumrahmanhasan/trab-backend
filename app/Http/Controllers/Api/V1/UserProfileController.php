<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $user->load('roles', 'permissions');
        return $this->ok('User profile', new UserResource($user));
    }
}
