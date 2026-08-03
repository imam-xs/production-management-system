<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

// the response shapes this API commits to, in one place
trait ResponseTrait
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    protected function data(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * A bare confirmation with nothing to return but the fact that it worked.
     */
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * 201 with the resource that was just created, including its Location.
     */
    protected function created(JsonResource $resource): JsonResponse
    {
        return $resource->response()->setStatusCode(201);
    }

    /**
     * 204: deleted, and there is nothing left to describe.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(status: 204);
    }
}
