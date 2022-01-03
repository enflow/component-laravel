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

        // Frame options (deprecated, use content security policy instead.
        if ($frameOptions = config('laravel.security_headers.frame_options', 'SAMEORIGIN')) {
            $response->headers->set('X-Frame-Options', $frameOptions, false);
        }

        // Referrer Policy is a new header that allows a site to control how much information the browser includes with navigations away from a document and should be set by all sites.
        if ($request->isSecure() && ($referrerPolicy = config('laravel.security_headers.referrer_policy', 'strict-origin-when-cross-origin'))) {
            $response->headers->set('Referrer-Policy', $referrerPolicy, false);
        }

        // Expect-CT allows a site to determine if they are ready for the upcoming Chrome requirements and/or enforce their CT policy.
        if ($expectCt = config('laravel.security_headers.expect_ct', 'max-age=0, report-uri="https://enflow.report-uri.com/r/d/ct/reportOnly"')) {
            $response->headers->set('Expect-CT', $expectCt, false);
        }

        // Preventing Clickjacking
        // frame-ancestors 'self'; -> breaks Beamy as the viewer iframes the screens
        if ($contentSecurityPolicy = config('laravel.security_headers.content_security_policy', "report-uri \"https://enflow.report-uri.com/r/d/csp/reportOnly\"")) {
            $response->headers->set('Content-Security-Policy', $contentSecurityPolicy, false);
        }

        // Disable unused permissions.
        if ($permissionPolicy = config('laravel.security_headers.permissions_policy', "accelerometer=(), gyroscope=(), magnetometer=(), microphone=(), usb=()")) {
            $response->headers->set('Permissions-Policy', $permissionPolicy, false);
        }

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

