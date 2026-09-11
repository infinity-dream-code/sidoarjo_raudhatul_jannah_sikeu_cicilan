<?php

namespace App\Http\Middleware;

use App\Support\PersistentLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestorePersistentLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            PersistentLogin::restore();
        }

        $response = $next($request);

        if (Auth::check()) {
            PersistentLogin::set(Auth::user());
        }

        return $response;
    }
}
