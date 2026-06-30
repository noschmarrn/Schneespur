<?php

namespace Tests\Feature;

use App\Services\Extension\PublicHomepageRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RobotsIndexingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function markInstalled(): void
    {
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_robots_txt_blocks_everything_when_no_homepage_registered(): void
    {
        $this->markInstalled();

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('User-agent: *', $response->getContent());
        $this->assertStringContainsString('Disallow: /', $response->getContent());
        $this->assertStringNotContainsString('Allow:', $response->getContent());
    }

    public function test_robots_txt_allows_homepage_and_declared_paths_when_active(): void
    {
        $this->markInstalled();

        $registry = app(PublicHomepageRegistry::class);
        $registry->register(fn () => response('home', 200));
        $registry->allowCrawling('/leistungen', '/impressum');
        $registry->setSitemapUrl('https://example.test/sitemap.xml');

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /', $content);
        $this->assertStringContainsString('Allow: /$', $content);
        $this->assertStringContainsString('Allow: /leistungen', $content);
        $this->assertStringContainsString('Allow: /impressum', $content);
        $this->assertStringContainsString('Allow: /build/', $content);
        $this->assertStringContainsString('Sitemap: https://example.test/sitemap.xml', $content);
    }

    public function test_robots_txt_default_denies_before_installation(): void
    {
        // No installed.lock: the installer redirect must NOT swallow robots.txt.
        @unlink(storage_path('app/installed.lock'));

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Disallow: /', $content);
        $this->assertStringNotContainsString('Allow:', $content);
    }

    public function test_private_pages_carry_noindex_header(): void
    {
        $this->markInstalled();

        // No homepage registered: "/" redirects to login and must stay private.
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_homepage_has_no_noindex_header(): void
    {
        $this->markInstalled();

        app(PublicHomepageRegistry::class)->register(
            fn () => response('<h1>Winterdienst Mustermann</h1>', 200)
        );

        $response = $this->get('/')->assertOk();

        $response->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_other_paths_stay_private_even_when_homepage_active(): void
    {
        $this->markInstalled();

        // A public homepage is active, but only "/" is crawlable. The login
        // page must still carry the noindex header.
        app(PublicHomepageRegistry::class)->register(fn () => response('home', 200));

        $this->get(route('login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_is_crawlable_matches_paths_and_subpaths_but_not_prefixes(): void
    {
        $registry = app(PublicHomepageRegistry::class);
        $registry->register(fn () => response('home', 200));
        $registry->allowCrawling('/leistungen');

        $this->assertTrue($registry->isCrawlable('/'));
        $this->assertTrue($registry->isCrawlable('leistungen'));            // no leading slash
        $this->assertTrue($registry->isCrawlable('/leistungen/winterdienst')); // sub-path
        $this->assertFalse($registry->isCrawlable('/leistungenX'));         // mere prefix
        $this->assertFalse($registry->isCrawlable('/login'));
    }
}
