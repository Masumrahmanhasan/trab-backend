<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Traits\ApiResponse;

class RegistrationController extends Controller
{
    use ApiResponse;
    public function register(RegisterRequest $request)
    {
        return $this->ok('Register successfully');
    }
}
