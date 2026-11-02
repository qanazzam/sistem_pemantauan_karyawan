<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DesktopOptimizationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Tell Chromium not to hold idle TCP keep-alive sockets on the single-threaded PHP CLI server.
        // This ensures subsequent link clicks are accepted immediately without waiting on socket backlog.
        $response->headers->set('Connection', 'close');

        // Allow static caching of assets for maximum desktop responsiveness
        if ($request->is('images/*', 'vendor/*', 'css/*', 'js/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=604800, immutable');
        }

        return $response;
    }
}
