<?php

namespace App\Http\Middleware;

use App\Services\Extension\PublicHomepageRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function __construct(private PublicHomepageRegistry $homepage) {}

    /**
     * Add baseline browser-hardening headers to every web response.
     *
     * Clickjacking (X-Frame-Options), MIME-sniffing (X-Content-Type-Options)
     * and referrer leakage (Referrer-Policy) are always set; HSTS is added only
     * on HTTPS responses so plain-HTTP local development is unaffected.
     *
     * Search-engine exposure is denied by default via X-Robots-Tag: the whole
     * installation stays out of search results to protect customer data. The
     * header is the authoritative, content-type-agnostic signal (it also covers
     * PDF reports and other non-HTML responses that have no <meta> tag). It is
     * omitted only for the public paths a frontpage module opts in via the
     * PublicHomepageRegistry, so that website can actually be indexed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! ($this->homepage->has() && $this->homepage->isCrawlable($request->path()))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
