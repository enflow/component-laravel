<?php

namespace Enflow\Component\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RemoveIndexPhp
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->getBaseUrl() === '/index.php') {
            return redirect(Str::replaceFirst('/index.php', '', $request->fullUrl()));
        }

        return $next($request);
    }
}
