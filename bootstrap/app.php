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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: '/login',
            users: function () {
                $user = auth()->user();

                // If no user is found for some reason, default to login
                if (!$user) {
                    return '/login';
                }

                // Super Admins and Librarians use the admin panel (matches User::canAccessPanel)
                if ($user->isSuperAdmin() || $user->isLibrarian()) {
                    return '/admin';
                }

                return '/user';
            }
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();