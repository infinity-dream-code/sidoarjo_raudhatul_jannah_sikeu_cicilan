<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RestorePersistentLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            try {
                PersistentLogin::restore();
            } catch (Throwable) {
            }
        }

        $response = $next($request);

        if (Auth::check()) {
            try {
                PersistentLogin::set(Auth::user());
            } catch (Throwable) {
            }
        }

        return $response;
    }
}
