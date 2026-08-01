<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Envelope for the handful of endpoints that don't return an Eloquent API
 * Resource — a login token, a computed traceability tree, a plain
 * confirmation message. Model-backed responses go through their Resource
 * class instead, which already wraps in `{"data": ...}` on its own.
 */
class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    public static function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
