<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\SchneespurModuleClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ModuleTrustLevelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $stateFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateFilePath = storage_path('app/schneespur_modules_state_test_trust.json');
        config(['schneespur_modules.state_file_path' => $this->stateFilePath]);

        @unlink($this->stateFilePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFilePath);
        parent::tearDown();
    }

    public function test_normalize_module_extracts_trust_level_from_catalog(): void
    {
        $client = app(SchneespurModuleClient::class);
        $method = new \ReflectionMethod($client, 'normalizeModule');

        $raw = [
            'slug' => 'test-module',
            'name' => ['de' => 'Test'],
            'description' => ['de' => 'Beschreibung'],
            'current_version' => '1.0.0',
            'trust_level' => 'official',
        ];

        $result = $method->invoke($client, $raw);

        $this->assertSame('official', $result['trust_level']);
    }

    public function test_normalize_module_defaults_trust_level_to_community_when_missing(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'trust_level missing')
                    && $context['slug'] === 'no-trust';
            });

        $client = app(SchneespurModuleClient::class);
        $method = new \ReflectionMethod($client, 'normalizeModule');

        $raw = [
            'slug' => 'no-trust',
            'name' => ['de' => 'Test'],
            'description' => ['de' => 'Beschreibung'],
            'current_version' => '1.0.0',
        ];

        $result = $method->invoke($client, $raw);

        $this->assertSame('community', $result['trust_level']);
    }

    public function test_trust_level_is_fillable_on_module_model(): void
    {
        $module = Module::create([
            'slug' => 'fillable-test',
            'version' => '1.0.0',
            'enabled' => true,
            'trust_level' => 'verified',
            'installed_at' => now(),
        ]);

        $this->assertSame('verified', $module->fresh()->trust_level);
    }

    public function test_trust_level_column_is_nullable(): void
    {
        $module = Module::create([
            'slug' => 'nullable-test',
            'version' => '1.0.0',
            'enabled' => true,
            'installed_at' => now(),
        ]);

        $this->assertNull($module->fresh()->trust_level);
    }

    public function test_trust_level_persisted_on_install_payload(): void
    {
        $module = Module::updateOrCreate(
            ['slug' => 'install-persist'],
            [
                'version' => '2.0.0',
                'enabled' => true,
                'manifest_json' => ['slug' => 'install-persist'],
                'signature_status' => 'signed',
                'trust_level' => 'official',
                'installed_at' => now(),
            ],
        );

        $this->assertSame('official', $module->fresh()->trust_level);
    }

    public function test_trust_level_updated_on_module_update(): void
    {
        Module::create([
            'slug' => 'update-persist',
            'version' => '1.0.0',
            'enabled' => true,
            'trust_level' => 'community',
            'installed_at' => now(),
        ]);

        Module::where('slug', 'update-persist')->update([
            'version' => '2.0.0',
            'trust_level' => 'verified',
        ]);

        $this->assertSame('verified', Module::where('slug', 'update-persist')->first()->trust_level);
    }
}
