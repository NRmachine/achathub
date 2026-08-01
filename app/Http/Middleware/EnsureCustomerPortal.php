<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return redirect()->route('admin.index');
        }

        if ($user->role === 'reseller' || $user->resellerRequest()->exists()) {
            $destination = $user->role === 'reseller' ? 'pro.index' : 'reseller.dashboard';

            return redirect()->route($destination)->with('error', 'Utilisez votre espace professionnel pour gérer ce compte.');
        }

        return $next($request);
    }
}
