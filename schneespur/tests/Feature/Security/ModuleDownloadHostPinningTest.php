<?php

namespace Tests\Feature\Security;

use App\Services\SchneespurModuleClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ModuleDownloadHostPinningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['schneespur_modules.server_url' => 'https://jenni.noschmarrn.dev']);
        // Guard against any real outbound request during these tests.
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_rejects_download_url_on_a_foreign_host(): void
    {
        $client = new SchneespurModuleClient();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Host/');

        // A catalog-supplied URL pointing off the configured module server must
        // be refused before any request is made (blind-SSRF guard), even when
        // its declared size/sha would otherwise be accepted.
        $client->downloadModule('evil', 'https://evil.example/mod.zip', hash('sha256', 'x'), 1);
    }

    public function test_allows_download_url_on_the_configured_host(): void
    {
        $client = new SchneespurModuleClient();

        // A URL on the configured module host must pass the host gate and
        // proceed to the normal size/sha verification — proving the pinning
        // does not over-block legitimate (jenni.noschmarrn.dev) downloads.
        try {
            $client->downloadModule('dokumente', 'https://jenni.noschmarrn.dev/dokumente', hash('sha256', 'x'), 999);
            $this->fail('expected a size/sha mismatch after the host gate passed');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('Host', $e->getMessage());
        }
    }
}
