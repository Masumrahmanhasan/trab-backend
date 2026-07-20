<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Traits\ApiResponse;

class LoginController extends Controller
{
    use ApiResponse;
    public function login(LoginRequest $request)
    {
        return $this->ok($request->input('email'));
    }
}
