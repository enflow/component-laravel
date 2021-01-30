<?php

namespace Enflow\Component\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        // Referrer Policy is a new header that allows a site to control how much information the browser includes with navigations away from a document and should be set by all sites.
        if ($request->isSecure()) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        // Expect-CT allows a site to determine if they are ready for the upcoming Chrome requirements and/or enforce their CT policy.
        $response->headers->set('Expect-CT', 'max-age=0, report-uri="https://enflow.report-uri.com/r/d/ct/reportOnly"');

        // Preventing Clickjacking
        // frame-ancestors 'self'; -> breaks Beamy as the viewer iframes the screens
        $response->headers->set('Content-Security-Policy', "report-uri \"https://enflow.report-uri.com/r/d/csp/reportOnly\"", false);

        // Feature policy
        $response->headers->set('Feature-Policy', "accelerometer *; camera *; geolocation *; gyroscope *; microphone *; payment *; magnetometer 'none'; usb 'none'");

        // HSTS (activated per domain)
        $hosting = $this->hosting();
        if (!empty($hosting) && !empty($hosting->domain->hsts)) {
            $response->headers->set('Strict-Transport-Security', "max-age=31536000; includeSubdomains; preload");
        }

        return $response;
    }

    private function hosting()
    {
        $hosting = cache()->rememberForever(Str::slug(config('app.name')) . ':hosting.json', function () {
            if (file_exists($path = base_path('../../hosting.json'))) {
                return @json_decode(file_get_contents($path));
            }

            return true;
        });

        // Cannot save 'false' to caching.
        return $hosting === true ? null : $hosting;
    }
}

