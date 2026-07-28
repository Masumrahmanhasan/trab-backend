<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService implements AuthenticationServiceInterface
{
    protected SubscriptionServiceInterface $subscriptionService;

    public function __construct(SubscriptionServiceInterface $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Authenticate user with credentials
     */
    public function authenticate(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $this->validateCredentials($user, $password)) {
            Log::warning('Authentication failed', ['email' => $email]);

            return null;
        }

        Log::info('User authenticated successfully', ['user_id' => $user->id, 'email' => $email]);

        return $user;
    }

    /**
     * Validate user credentials
     */
    public function validateCredentials(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Register new user with subscription setup
     */
    public function registerWithSubscription(array $userData, ?Store $store = null): User
    {
        try {
            $user = $this->register($userData);

            // Create subscription with automatic setup
            $this->subscriptionService->createSubscriptionWithAutoSetup($user, $store);

            Log::info('User registered with subscription', [
                'user_id' => $user->id,
                'email' => $user->email,
                'store_id' => $store?->id,
            ]);

            return $user;
        } catch (\Exception $e) {
            Log::error('User registration failed', [
                'email' => $userData['email'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Registration failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Register new user
     */
    public function register(array $userData): User
    {
        $user = User::query()->create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
        ]);

        Log::info('User registered successfully', ['user_id' => $user->id, 'email' => $user->email]);

        return $user;
    }

    /**
     * Generate authentication token for user
     */
    public function generateToken(User $user, string $tokenName = 'auth_token'): string
    {
        $token = $user->createToken($tokenName)->plainTextToken;

        Log::info('Token generated for user', ['user_id' => $user->id, 'token_name' => $tokenName]);

        return $token;
    }
}
