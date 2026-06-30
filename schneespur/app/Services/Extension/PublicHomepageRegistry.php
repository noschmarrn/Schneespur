<?php

namespace App\Services\Extension;

/**
 * Lets a module take over the public root URL ("/") to serve a public homepage
 * instead of the default login redirect. A module registers a handler during
 * its ServiceProvider boot(); the core "/" route consults this registry at
 * request time.
 *
 * The same registry is the single source of truth for search-engine exposure.
 * The whole installation is kept out of search engines by default (login,
 * admin, portal, driver, installer all stay private to protect customer data).
 * A module that ships a public website opts specific paths back in via
 * {@see allowCrawling()}; three layers then consult this registry at request
 * time so they never drift apart:
 *   - the dynamic /robots.txt route (what may be crawled),
 *   - the X-Robots-Tag response header in SecurityHeaders (what may be indexed),
 *   - the per-page <meta name="robots"> tag in the layouts.
 */
class PublicHomepageRegistry
{
    /** @var (callable(): mixed)|null */
    protected $handler = null;

    /**
     * Public paths search engines may crawl and index, e.g. "/" or
     * "/leistungen". Everything not listed here stays private.
     *
     * @var list<string>
     */
    protected array $crawlablePaths = [];

    /** Absolute URL of an XML sitemap to advertise in robots.txt, if any. */
    protected ?string $sitemapUrl = null;

    /**
     * Register the handler that produces the homepage response.
     * The handler may return a Response, a View, or a string.
     *
     * Serving a public homepage implies the root URL is public, so "/" is
     * marked crawlable automatically.
     *
     * @param  callable(): mixed  $handler
     */
    public function register(callable $handler): void
    {
        $this->handler = $handler;
        $this->allowCrawling('/');
    }

    public function has(): bool
    {
        return $this->handler !== null;
    }

    /**
     * Invoke the registered handler to produce the homepage response.
     */
    public function render(): mixed
    {
        return ($this->handler)();
    }

    /**
     * Declare additional public paths that search engines may crawl and index,
     * e.g. "/leistungen" or "/impressum". A leading slash is optional. The
     * root "/" is added automatically by {@see register()}.
     */
    public function allowCrawling(string ...$paths): void
    {
        foreach ($paths as $path) {
            $path = $this->normalize($path);
            if (! in_array($path, $this->crawlablePaths, true)) {
                $this->crawlablePaths[] = $path;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function crawlablePaths(): array
    {
        return $this->crawlablePaths;
    }

    /**
     * Whether the given request path may be crawled/indexed. The root matches
     * only itself; a section path also matches its sub-paths (so registering
     * "/leistungen" covers "/leistungen/winterdienst") but never a different
     * path that merely shares a prefix (so it never covers "/leistungenX").
     */
    public function isCrawlable(string $path): bool
    {
        $path = $this->normalize($path);

        foreach ($this->crawlablePaths as $allowed) {
            if ($allowed === '/') {
                if ($path === '/') {
                    return true;
                }
            } elseif ($path === $allowed || str_starts_with($path, $allowed.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Advertise an XML sitemap URL in robots.txt. The module is responsible for
     * actually serving the sitemap at this URL.
     */
    public function setSitemapUrl(string $url): void
    {
        $this->sitemapUrl = $url;
    }

    public function sitemapUrl(): ?string
    {
        return $this->sitemapUrl;
    }

    /**
     * Normalize a path to a single leading slash and no trailing slash, so
     * "/", "leistungen" and "/leistungen/" compare consistently.
     */
    protected function normalize(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path;
    }
}
