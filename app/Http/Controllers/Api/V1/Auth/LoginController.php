<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request)
    {
        $request->validated($request->all());
        $key = $this->throttleKey($request);
        $this->checkRateLimit($key);

        $user = User::query()->where('email', $request->validated(['email']))->first();
        if (!$user || !Hash::check($request->validated(['password']), $user->password)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->ok('Authenticated', [
            'token' => $token,
        ]);
    }

    protected function throttleKey($request)
    {
        return Str::lower($request->input('email')).'|'.$request->ip();
    }

    protected function checkRateLimit($key)
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => "Too many login attempts. Please try again in $seconds seconds."]);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return $this->ok('You have been successfully logged out!');
    }
}
