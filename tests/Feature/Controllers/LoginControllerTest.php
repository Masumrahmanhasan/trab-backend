<?php

use App\Models\User;
use App\Services\Contracts\AuthenticationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

describe('login', function () {
    it('validates the email and password are required', function () {
        $response = postJson('/api/v1/auth/login', [
            'email' => '',
            'password' => '',
        ]);
        expect($response->status())->toBe(422)
            ->and($response->json('errors'))->toHaveKeys(['email', 'password']);
    });

    it('validates the email is valid email', function () {
        $response = postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => 'secret',
        ]);
        expect($response->status())->toBe(422)
            ->and($response->json('errors.email'))->not->toBeEmpty();
    });

    it('fails with invalid credentials', function () {
        $mock = Mockery::mock(AuthenticationServiceInterface::class);
        $mock->allows('authenticate')->andReturn(null);

        $this->app->instance(AuthenticationServiceInterface::class, $mock);
        $response = postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);
        expect($response->status())->toBe(422)
            ->and($response->json('errors.email'))->not->toBeEmpty();
    });

    it('succeeds with valid credentials', function () {
        $user = User::factory()->create();

        $mock = Mockery::mock(AuthenticationServiceInterface::class);
        $mock->allows('authenticate')
            ->with($user->email, 'password')
            ->andReturn($user);

        $mock->expects('generateToken')
            ->with($user, 'trab_auth_token')
            ->andReturn('mocked-token');

        $this->app->instance(AuthenticationServiceInterface::class, $mock);
        $response = postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        expect($response->status())->toBe(200)
            ->and($response->json('data.token'))->toBe('mocked-token')
            ->and($response->json('message'))->toBe('Authenticated');
    });

    it('clears the rate limiter after successful login', function () {
        $user = User::factory()->create();
        RateLimiter::hit(strtolower($user->email) . '|127.0.0.1');
        $mock = Mockery::mock(AuthenticationServiceInterface::class);
        $mock->allows('authenticate')
            ->andReturn($user);
        $mock->allows('generateToken')
            ->andReturn('mocked-token');

        $this->app->instance(AuthenticationServiceInterface::class, $mock);
        postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $attempts = RateLimiter::attempts(strtolower($user->email) . '|127.0.0.1');
        expect($attempts)->toBe(0);
    });

    it('increments the rate limiter after failed login attempts', function () {
        $mock = Mockery::mock(AuthenticationServiceInterface::class);
        $mock->allows('authenticate')
            ->andReturn(null);
        $this->app->instance(AuthenticationServiceInterface::class, $mock);
        RateLimiter::clear('wrong@example.com|127.0.0.1');
        postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrong_password',
        ]);
        $attempts = RateLimiter::attempts(strtolower('wrong@example.com') . '|127.0.0.1');
        expect($attempts)->toBe(1);
    });

    it('blocks login after 5 failed attempts', function () {
        $mock = Mockery::mock(AuthenticationServiceInterface::class);
        $mock->allows('authenticate')
            ->andReturn(null);
        $this->app->instance(AuthenticationServiceInterface::class, $mock);
        $email = 'wrong@example.com';
        $key = strtolower($email) . '|127.0.0.1';
        RateLimiter::clear($email . $key);

        for ($i = 0; $i < 5; $i++) {
            postJson('/api/v1/auth/login', [
                'email' => 'wrong@example.com',
                'password' => 'wrong_password',
            ]);
        }

        RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);
        RateLimiter::shouldReceive('availableIn')->andReturn(30);

        $response = postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong_password',
        ]);

        expect($response->status())->toBe(422)
            ->and($response->json('errors.email'))->toContain('Too many login attempts. Please try again in 30 seconds.');
    });
});
