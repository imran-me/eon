<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If 2FA is enabled but not verified in this session, redirect to verify
        if ($user && $user->two_factor_confirmed_at && !session('two_factor_verified')) {
            Auth::logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Please complete 2FA verification.']);
        }

        return $next($request);
    }
}
