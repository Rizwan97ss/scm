<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Central JSON envelope so every API response (success or error) has the same shape:
 * { "success": bool, "message": string|null, "data": mixed, "meta": object|null, "errors": object|null }
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200, ?array $meta = null): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource || $data instanceof AnonymousResourceCollection
                ? $data->response()->getData(true)['data'] ?? $data
                : $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function created(mixed $data = null, ?string $message = 'Created successfully.'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function noContent(?string $message = 'Deleted successfully.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => null], 200);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
