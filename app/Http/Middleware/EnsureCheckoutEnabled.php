<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bag, checkout and order pages only work when config('shop.checkout_enabled') is on. */
class EnsureCheckoutEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('shop.checkout_enabled')) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Online ordering is not available.'], 404)
                : redirect()->route('shop.home');
        }

        return $next($request);
    }
}
