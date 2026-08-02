<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        protected SubscriptionServiceInterface $subscriptionService,
    ) {}

    /**
     * Authenticate a user with credentials.
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

    public function validateCredentials(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Register a new user and start their trial on the default plan.
     *
     * The default `user` role is assigned automatically by User::booted().
     *
     * @throws \Throwable
     */
    public function registerWithSubscription(array $userData, ?Store $store = null): User
    {
        return DB::transaction(function () use ($userData, $store) {
            $user = $this->register($userData);

            $this->subscriptionService->createSubscriptionWithAutoSetup($user, $store);

            Log::info('User registered with subscription', [
                'user_id' => $user->id,
                'email' => $user->email,
                'store_id' => $store?->id,
            ]);

            return $user;
        });
    }

    /**
     * Register a new user.
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
     * Generate an authentication token for the user.
     */
    public function generateToken(User $user, string $tokenName = 'auth_token'): string
    {
        $token = $user->createToken($tokenName)->plainTextToken;

        Log::info('Token generated for user', ['user_id' => $user->id, 'token_name' => $tokenName]);

        return $token;
    }
}
