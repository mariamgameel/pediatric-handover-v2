<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a standardized success JSON response.
     *
     * @param mixed $data
     * @param array<string, mixed>|null $meta
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param array<string, mixed>|null $details
     * @param array<string, mixed>|null $meta
     */
    public static function error(string $message, string $code, int $status = 400, ?array $details = null, ?array $meta = null): JsonResponse
    {
        $error = [
            'code' => $code,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        $payload = [
            'success' => false,
            'message' => $message,
            'error' => $error,
        ];

        if ($details !== null) {
            $payload['errors'] = $details;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
}
