<?php

use App\Http\Middleware\EnsureApiTokenHasAbility;
use App\Http\Middleware\EnsureUserHasCapability;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'capability' => EnsureUserHasCapability::class,
            'token_ability' => EnsureApiTokenHasAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiResponse::error(
                message: $e->getMessage(),
                code: 'validation_failed',
                errors: $e->errors(),
                status: 422,
            );
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Unauthenticated.',
                code: 'unauthenticated',
                status: 401,
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiResponse::error(
                message: 'Not found.',
                code: 'not_found',
                status: 404,
            );
        });

        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            $status = $e->getStatusCode();

            if ($status === 403) {
                return ApiResponse::error(
                    message: 'Forbidden.',
                    code: 'forbidden',
                    status: 403,
                );
            }

            if ($status === 429) {
                return ApiResponse::error(
                    message: 'Too many requests.',
                    code: 'rate_limited',
                    status: 429,
                );
            }

            return null;
        });
    })->create();
