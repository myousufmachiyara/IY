<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Illuminate\Foundation\Configuration\Middleware $middleware) {
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \App\Http\Middleware\CheckModulePermission::class, // was Spatie's — now module-aware
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Illuminate\Foundation\Configuration\Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Session expired.'], 419)
                : redirect()->route('login')->with('error', 'Your session expired. Please log in again.');
        });
    })->create();
