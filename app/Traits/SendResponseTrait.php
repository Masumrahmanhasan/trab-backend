<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

trait SendResponseTrait
{
    public function success(mixed $data = [], string $message = '', bool $success = true, int $code = 200): JsonResponse
    {
        $response = [
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];

        if (! app()->environment('production')) {
            $response['debug'] = DB::getQueryLog();
        }

        return new JsonResponse($response, $code);
    }

    public function failed(mixed $data = [], string $message = '', int $code = 400): JsonResponse
    {
        return $this->success(data: $data, message: $message, success: false, code: $code);
    }
}
