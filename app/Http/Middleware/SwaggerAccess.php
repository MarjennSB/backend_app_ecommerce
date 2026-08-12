<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('l5-swagger.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
