<?php

namespace App\Http\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function success(string $message, ?array $data = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function error(string $message, string $errorCode, int $status = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ], $extra), $status);
    }
}
