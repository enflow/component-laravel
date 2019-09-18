<?php

namespace Enflow\Component\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Robots
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof Response && config('laravel.robots_headers', true)) {
            $response->headers->set('x-robots-tag', app()->environment('production') ? 'all' : 'none', false);
        }

        return $response;
    }
}
