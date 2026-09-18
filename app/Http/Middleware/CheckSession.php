<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (!\Illuminate\Support\Facades\Auth::check()) {
            \App\Support\PersistentLogin::restore();
        }

        if (\Illuminate\Support\Facades\Auth::check() || session()->has('user')) {
            return $next($request);
        }

        if (\App\Support\PersistentLogin::hasCookie()) {
            return response()->view('errors.500', [], 500);
        }

        return redirect()->route('login');
    }
}
