<?php

namespace App\Services\Contracts;

use App\Models\Store;
use App\Models\User;

interface AuthenticationServiceInterface
{
    /**
     * Authenticate a user with credentials
     */
    public function authenticate(string $email, string $password): ?User;

    /**
     * Register a new user
     */
    public function register(array $userData): User;

    /**
     * Register a new user with subscription setup
     */
    public function registerWithSubscription(array $userData, ?Store $store = null): User;

    /**
     * Generate authentication token for a user
     */
    public function generateToken(User $user, string $tokenName = 'auth_token'): string;

    /**
     * Validate user credentials
     */
    public function validateCredentials(User $user, string $password): bool;
}
