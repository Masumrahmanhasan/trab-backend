<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Models\User;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use ApiResponse;

    protected AuthenticationServiceInterface $authService;

    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $key = $this->throttleKey($request);
        $this->checkRateLimit($key);

        $user = $this->authService->authenticate($validated['email'], $validated['password']);

        if (!$user) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($key);

        $token = $this->authService->generateToken($user, 'trab_auth_token');

        return $this->ok('Authenticated', new LoginResource([
            'token' => $token,
        ]));
    }

    protected function throttleKey(Request $request): string
    {
        return Str::lower($request->input('email')) . '|' . $request->ip();
    }

    protected function checkRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => "Too many login attempts. Please try again in {$seconds} seconds."]);
        }
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->ok('Email already verified');
        }

        $request->user()->sendEmailVerificationNotification();

        return $this->ok('Verification link sent');
    }

    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->forbidden('Invalid verification link');
        }

        if ($user->hasVerifiedEmail()) {
            return $this->ok('Email already verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->ok('Email successfully verified');
    }
}
