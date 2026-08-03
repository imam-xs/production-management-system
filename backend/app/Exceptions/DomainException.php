<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

abstract class DomainException extends RuntimeException
{
    abstract public function statusCode(): int;

    /**
     * @return array<string, mixed>
     */
    abstract public function context(): array;

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => class_basename(static::class),
            'message' => $this->getMessage(),
            ...$this->context(),
        ], $this->statusCode());
    }
}
