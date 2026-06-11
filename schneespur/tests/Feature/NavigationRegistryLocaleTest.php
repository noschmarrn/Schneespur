<?php

namespace Tests\Feature;

use App\Services\Extension\FilterRegistry;
use App\Services\Extension\NavigationRegistry;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class NavigationRegistryLocaleTest extends TestCase
{
    private function registry(): NavigationRegistry
    {
        // Fresh instance so the boot-time core items don't leak in.
        return new NavigationRegistry(app(FilterRegistry::class));
    }

    public function test_item_labels_resolve_to_the_current_locale_at_read_time(): void
    {
        app('translator')->addLines(['nav.demo' => 'Kunden'], 'de');
        app('translator')->addLines(['nav.demo' => 'Clients'], 'fr');

        $reg = $this->registry();
        $reg->addGroup('g', 'g.heading', 0);
        $reg->addItem(group: 'g', slug: 'demo', label: 'nav.demo', route: 'admin.dashboard', icon: 'x');

        App::setLocale('fr');

        $items = $reg->getItems();

        $this->assertSame('Clients', $items['g'][0]['label']);
    }

    public function test_group_labels_resolve_to_the_current_locale_at_read_time(): void
    {
        app('translator')->addLines(['nav.section' => 'Stammdaten'], 'de');
        app('translator')->addLines(['nav.section' => 'Donnees'], 'fr');

        $reg = $this->registry();
        $reg->addGroup('g', 'nav.section', 0);

        App::setLocale('fr');

        $groups = $reg->getGroups();

        $this->assertSame('Donnees', $groups[0]['label']);
    }

    public function test_empty_group_label_stays_empty(): void
    {
        $reg = $this->registry();
        $reg->addGroup('top', '', 0);

        $this->assertSame('', $reg->getGroups()[0]['label']);
    }

    /**
     * End-to-end across registration + registry: the real core nav registered in
     * AppServiceProvider::registerCoreNavigation() must follow the active locale,
     * not the boot-time locale it was registered under.
     */
    public function test_core_admin_nav_follows_the_active_locale(): void
    {
        $nav = app(NavigationRegistry::class);

        App::setLocale('de');
        $de = $this->coreLabel($nav, 'customers');

        App::setLocale('en');
        $en = $this->coreLabel($nav, 'customers');

        $this->assertSame('Kunden', $de);
        $this->assertSame('Customers', $en);
    }

    private function coreLabel(NavigationRegistry $nav, string $slug): ?string
    {
        foreach ($nav->getItems() as $groupItems) {
            foreach ($groupItems as $item) {
                if ($item['slug'] === $slug) {
                    return $item['label'];
                }
            }
        }

        return null;
    }
}
