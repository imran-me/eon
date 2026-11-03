<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use App\Exceptions\SettlementSourceOverdrawnException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. Add to the 'web' middleware group (runs on all web routes)
        $middleware->web(append: [
            \App\Http\Middleware\UpdateUserLastSeen::class,
        ]);
         // Register Spatie permission middleware aliases
        $middleware->alias([
            'role'                => RoleMiddleware::class,
            'permission'          => PermissionMiddleware::class,
            'role_or_permission'  => RoleOrPermissionMiddleware::class,
            'two_factor'          => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        // Every money-posting API uses the same domain exception. Returning it
        // here keeps controllers consistent and, because the exception escapes
        // the DB::transaction callback, guarantees the whole posting is rolled
        // back before the client receives the validation-style response.
        $exceptions->render(function (SettlementSourceOverdrawnException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => ['amount' => [$e->getMessage()]],
                ], 422);
            }
        });
    })->create();
