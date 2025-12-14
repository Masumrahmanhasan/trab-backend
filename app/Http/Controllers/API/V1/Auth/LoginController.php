<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Traits\SendResponseTrait;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

final class LoginController extends Controller
{
    use SendResponseTrait;

    /**
     * Handle the incoming request.
     *
     * @throws ConnectionException
     * @throws Throwable
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $payload = [
            'grant_type' => 'password',
            'client_id' => config('services.passport.client_id'),
            'client_secret' => config('services.passport.client_secret'),
            'username' => $request->validated('email'),
            'password' => $request->validated('password'),
            'scope' => '',
        ];

        $response = Http::asForm()->post(url('/oauth/token'), $payload);

        if ($response->failed()) {
            return $this->failed(message: $response->json()['error_description'], code: ResponseAlias::HTTP_UNAUTHORIZED);
        }

        return $this->success($response->json(), __('auth.success'), code: ResponseAlias::HTTP_OK);
    }
}
