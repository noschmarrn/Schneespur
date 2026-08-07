<?php

namespace Tests\Feature;

use App\Services\Pdf\DomPdfRenderer;
use Tests\TestCase;

/**
 * dompdf must not fetch remote resources on our behalf (M018/S05).
 *
 * No core PDF needs it: photos are inlined as data: URIs, the logo is an
 * absolute file path and the GPS track is an inline SVG. Leaving the fetcher on
 * only donates an SSRF surface to whatever the next dompdf advisory turns out
 * to be — six of the CVEs closed in this slice needed remote access to bite.
 *
 * This asserts the contract rather than dompdf's behaviour on purpose: dompdf
 * fetches through curl/file_get_contents, so Http::fake() cannot observe it and
 * a test that provoked a real fetch would put the network in the suite.
 */
class PdfRemoteResourcesTest extends TestCase
{
    public function test_remote_resources_are_disabled_by_default(): void
    {
        $options = (new DomPdfRenderer)->resolveOptions([]);

        $this->assertFalse($options['isRemoteEnabled']);
    }

    public function test_a_caller_can_still_opt_in_deliberately(): void
    {
        $options = (new DomPdfRenderer)->resolveOptions(['isRemoteEnabled' => true]);

        $this->assertTrue($options['isRemoteEnabled']);
    }

    public function test_paper_and_orientation_keep_their_defaults(): void
    {
        $options = (new DomPdfRenderer)->resolveOptions([]);

        $this->assertSame('a4', $options['paper']);
        $this->assertSame('portrait', $options['orientation']);
    }

    public function test_no_render_path_hardcodes_remote_access_back_on(): void
    {
        // renderFooter() used to pass a literal true, which would have quietly
        // undone the default above for every report that renders a footer.
        $source = file_get_contents(app_path('Services/Pdf/DomPdfRenderer.php'));

        $this->assertStringNotContainsString("setOption('isRemoteEnabled', true)", $source);
        $this->assertStringNotContainsString("'isRemoteEnabled'] ?? true", $source);
    }
}
