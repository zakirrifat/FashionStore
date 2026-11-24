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
    ->withMiddleware(function (Middleware $middleware) {
        // Signed-in customers who open /login or /register go to their account.
        $middleware->redirectUsersTo(fn () => route('account.overview'));
        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias(['checkout' => \App\Http\Middleware\EnsureCheckoutEnabled::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
