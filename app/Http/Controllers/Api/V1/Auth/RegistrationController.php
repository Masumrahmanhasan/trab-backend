<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;
use function __;
use function config;

class RegistrationController extends Controller
{
    use ApiResponse;

    protected AuthenticationServiceInterface $authService;

    public function __construct(AuthenticationServiceInterface $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @throws Throwable
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $key = $this->throttleKey($request);
        $this->checkRateLimit($key);

        $user = $this->authService->registerWithSubscription($validated);

        RateLimiter::clear($key);
        $token = $this->authService->generateToken($user, config('app.name').'-token');
        return $this->ok(__('messages.login'), new LoginResource([
            'token' => $token,
            'user' => $user,
        ]));
    }

    protected function throttleKey(Request $request): string
    {
        return 'register|'.$request->ip().'|'.$request->method();
    }

    protected function checkRateLimit(string $key): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => "Too many Registration attempts. Please try again in {$seconds} seconds."]);
        }
    }
}
