<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

// the response shapes this API commits to, in one place
trait ResponseTrait
{
    /** @param array<string, mixed>|list<mixed> $data */
    protected function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    // a bare confirmation, nothing to return but the fact that it worked
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function created(JsonResource $resource): JsonResponse
    {
        return $resource->response()->setStatusCode(201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(status: 204);
    }
}
