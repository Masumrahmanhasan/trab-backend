<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function ok($message, $data = []): JsonResponse
    {
        return $this->success($message, $data);
    }

    protected function success($message, $data = [], $statusCode = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'status' => $statusCode,
            'data' => $data
        ]);
    }

    protected function error($message, $statusCode = 400): JsonResponse
    {
        return $this->success($message, $statusCode);
    }
}
