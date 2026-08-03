<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business-rule refusals are raised with abort(409, ...). With
        // APP_DEBUG on, Laravel would attach the exception class, file and
        // stack trace to those; an API client wants the message and nothing
        // else. Validation (422), auth and 404 keep their default shapes,
        // and ForceJsonResponse guarantees all of them render as JSON.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], $e->getStatusCode())
                : null;
        });
    })->create();
