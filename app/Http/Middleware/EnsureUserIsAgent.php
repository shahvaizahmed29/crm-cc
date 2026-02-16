<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAgent()) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
