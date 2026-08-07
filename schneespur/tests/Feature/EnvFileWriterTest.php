<?php

namespace Tests\Feature;

use App\Services\Installer\EnvFileWriter;
use Tests\TestCase;

/**
 * EnvFileWriter must cope with a missing .env without raising PHP warnings
 * (M018/S06).
 *
 * A missing .env is not exceptional: it is the state of a fresh checkout, of
 * CI, and of every install before the wizard has run. A warning on that path
 * ends up in the test output for unrelated tests and turns the whole suite
 * yellow, which is how a CI gate becomes decoration.
 */
class EnvFileWriterTest extends TestCase
{
    /** @var list<string> */
    private array $raised = [];

    private function captureWarnings(): void
    {
        // Deliberately does NOT honour @-suppression: this asserts that no
        // warning is raised at all, not merely that none is displayed.
        set_error_handler(function (int $severity, string $message): bool {
            $this->raised[] = $message;

            return true;
        }, E_WARNING);
    }

    public function test_reading_a_missing_env_file_emits_no_warning(): void
    {
        $envPath = base_path('.env');
        $backup = file_exists($envPath) ? file_get_contents($envPath) : null;

        if ($backup !== null) {
            unlink($envPath);
        }

        $this->captureWarnings();

        try {
            $value = app(EnvFileWriter::class)->get('APP_KEY');
        } finally {
            restore_error_handler();

            if ($backup !== null) {
                file_put_contents($envPath, $backup);
            }
        }

        $this->assertSame([], $this->raised, 'Reading a missing .env must not raise a PHP warning');
        $this->assertNull($value);
    }

    public function test_the_full_content_of_a_missing_env_file_is_an_empty_string(): void
    {
        $envPath = base_path('.env');
        $backup = file_exists($envPath) ? file_get_contents($envPath) : null;

        if ($backup !== null) {
            unlink($envPath);
        }

        $this->captureWarnings();

        try {
            $content = app(EnvFileWriter::class)->getFullContent();
        } finally {
            restore_error_handler();

            if ($backup !== null) {
                file_put_contents($envPath, $backup);
            }
        }

        $this->assertSame([], $this->raised);
        $this->assertSame('', $content);
    }
}
