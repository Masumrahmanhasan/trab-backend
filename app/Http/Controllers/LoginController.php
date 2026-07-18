<?php

namespace App\Http\Controllers;

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
