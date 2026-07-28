<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'status' => 422,
                    'data' => [
                        'errors' => $e->errors(),
                    ],
                ], 422);
            }
        });

        $this->renderable(function (\InvalidArgumentException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'status' => 400,
                    'data' => [],
                ], 400);
            }
        });

        $this->renderable(function (\RuntimeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'status' => 500,
                    'data' => [],
                ], 500);
            }
        });
    }

    protected function prepareJsonResponse($request, Throwable $e): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage($e),
            'status' => $this->isHttpException($e) ? $e->getStatusCode() : 500,
            'data' => [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ], $this->isHttpException($e) ? $e->getStatusCode() : 500);
    }

    protected function getMessage(Throwable $e): string
    {
        return $this->isHttpException($e) ? $e->getMessage() : 'Server error';
    }
}
