<?php

namespace Tests\Feature\Security;

use App\Services\SchneespurModuleInstaller;
use ReflectionMethod;
use Tests\TestCase;
use ZipArchive;

class ModuleZipValidatorTest extends TestCase
{
    private function validate(array $entryNames): bool
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'modzip-');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('module.json', '{"slug":"x"}');
        foreach ($entryNames as $name) {
            $zip->addFromString($name, 'payload');
        }
        $zip->close();

        $reader = new ZipArchive();
        $reader->open($zipPath);

        $installer = app(SchneespurModuleInstaller::class);
        $method = new ReflectionMethod($installer, 'validateZipEntries');
        $method->setAccessible(true);
        $ok = (bool) $method->invoke($installer, $reader, 'x');

        $reader->close();
        @unlink($zipPath);

        return $ok;
    }

    public function test_clean_zip_passes(): void
    {
        $this->assertTrue($this->validate(['src/Provider.php', 'resources/views/x.blade.php']));
    }

    public function test_windows_absolute_path_entry_is_rejected(): void
    {
        $this->assertFalse($this->validate(['C:/windows/evil.php']));
    }

    public function test_backslash_entry_is_rejected(): void
    {
        $this->assertFalse($this->validate(['foo\\..\\bar.php']));
    }

    public function test_dot_dot_and_absolute_entries_are_rejected(): void
    {
        $this->assertFalse($this->validate(['../escape.php']));
        $this->assertFalse($this->validate(['/etc/passwd']));
    }
}
