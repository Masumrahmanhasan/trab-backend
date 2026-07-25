<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $request->validated($request->all());
        $key = $this->throttleKey($request);
        $this->checkRateLimit($key);

        $user = $this->createUser($request->validated());

        RateLimiter::clear($key);
        $token = $user->createToken(config('app.name').'-token')->plainTextToken;
        return $this->ok(__('messages.login'), [
            'token' => $token
        ]);
    }

    protected function throttleKey($request)
    {
        return 'register|'.$request->ip().'|'.$request->method().'|'.$request->ip();
    }

    protected function checkRateLimit($key)
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages(['email' => "Too many Registration attempts. Please try again in $seconds seconds."]);
        }
    }

    protected function createUser(array $request)
    {
        return User::query()->create([
            'name' => $request['name'],
            'email' => $request['email'],
            'password' => $request['password']
        ]);
    }
}
