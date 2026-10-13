<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs('password.change.*')
            && ! $request->routeIs('logout')
        ) {
            return redirect()->route('password.change.edit');
        }

        return $next($request);
    }
}
