<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedReseller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $approved = $user?->role === 'reseller'
            && ! $user->blocked
            && $user->resellerRequest()->where('status', 'Approuvée')->exists();

        if (! $approved) {
            return redirect()->route('reseller.dashboard')->with('error', 'Votre espace professionnel sera accessible après validation de votre demande.');
        }

        return $next($request);
    }
}
