<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add browser protections without exposing implementation details.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "worker-src 'none'",
            "manifest-src 'self'",
        ];

        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user() || $request->is('administration/*', 'admin/*', 'mon-compte/*', 'pro/*', 'messagerie', 'mes-donnees')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        // The anonymous landing page is identical for visitors without cookies.
        // Removing the newly-created empty session makes it eligible for Vercel's
        // edge cache; Vary keeps every cart and authenticated response isolated.
        if (app()->isProduction()
            && $request->isMethod('GET')
            && $request->routeIs('home')
            && $request->query->count() === 0
            && ! $request->headers->has('Cookie')
            && $response->isSuccessful()) {
            $response->headers->remove('Set-Cookie');
            $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
            $response->headers->set('Vercel-CDN-Cache-Control', 'public, s-maxage=300, stale-while-revalidate=3600');
            $response->setVary('Cookie');
        }

        return $response;
    }
}
