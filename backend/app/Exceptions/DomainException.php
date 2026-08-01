<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base for every business-rule violation in the domain (insufficient stock, an
 * order that isn't pending, an item that still holds stock, ...).
 *
 * Each subclass defines its own render(), which is a native Laravel 11+
 * mechanism — the framework calls render() on any thrown exception that has
 * one, with no central Handler match-statement to keep in sync as exceptions
 * are added. That central switch is what this base class exists to avoid.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * The HTTP status this violation maps to: 422 for "the request is invalid
     * given current state" (insufficient stock, wrong item type), 409 for
     * "the target resource is not in a state this action allows" (an order
     * that already ran).
     */
    abstract public function statusCode(): int;

    /**
     * Structured detail beyond the message — always includes the identifiers a
     * client needs to react programmatically (which item, which order, ...).
     *
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
