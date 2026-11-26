<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every web page (shop and admin).
 * Files under /storage are served by the web server directly — set the same
 * headers there too (see docs/PROJECT_HANDBOOK.md, going live).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');              // don't guess file types (stops "image" files running as HTML)
        $headers->set('X-Frame-Options', 'SAMEORIGIN');                  // other sites can't put our pages in a frame (clickjacking)
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Over HTTPS: browsers use HTTPS for this site for the next year, even if someone types http://.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
