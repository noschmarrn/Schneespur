<?php

namespace Tests\Feature\Extension;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Extension\HelpTopicRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelpTopicRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_get_topics_resolves_array_title_by_locale(): void
    {
        app()->setLocale('de');
        $reg = app(HelpTopicRegistry::class);
        $reg->registerTopic('lager', ['de' => 'Lager', 'en' => 'Warehouse'], 'lager::help.index', ['de' => 'Bestände', 'en' => 'Stock']);

        $topics = $reg->getTopics();

        $this->assertSame('Lager', $topics['lager']['title']);
        $this->assertSame('Bestände', $topics['lager']['description']);
        $this->assertSame('lager::help.index', $topics['lager']['view']);
    }

    public function test_get_topics_resolves_lang_key_title(): void
    {
        $reg = app(HelpTopicRegistry::class);
        $reg->registerTopic('x', 'help.topic_jobs', 'x::view');

        $this->assertSame(__('help.topic_jobs'), $reg->getTopics()['x']['title']);
    }

    public function test_permission_gate_hides_topic_for_user_without_permission(): void
    {
        $reg = app(HelpTopicRegistry::class);
        $reg->registerTopic('secret', 'Secret', 'x::view', permission: 'lager.nonexistent.permission');

        $driver = User::create(['name' => 'D', 'email' => 'd@test.local', 'password' => Hash::make('x')]);
        $driver->role = UserRole::Driver;
        $driver->save();

        $this->assertArrayNotHasKey('secret', $reg->getTopics($driver->fresh()));
    }
}
