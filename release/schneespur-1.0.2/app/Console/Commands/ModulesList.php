<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\SchneespurModuleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ModulesList extends Command
{
    protected $signature = 'schneespur:modules-list';

    protected $description = 'List all installed modules with their state.';

    public function handle(SchneespurModuleClient $client): int
    {
        if (! Schema::hasTable('modules')) {
            $this->info('Keine Module installiert.');
            return 0;
        }

        $modules = Module::orderBy('slug')->get();

        if ($modules->isEmpty()) {
            $this->info('Keine Module installiert.');
            return 0;
        }

        $rows = $modules->map(fn (Module $m) => [
            $m->slug,
            $m->version ?? '—',
            $m->enabled ? '✓' : '✗',
            $m->installed_at?->format('Y-m-d H:i') ?? '—',
        ])->toArray();

        $this->table(['Slug', 'Version', 'Aktiv', 'Installiert am'], $rows);

        $state = $client->loadState();
        $orphans = $state['orphans'] ?? [];

        if (! empty($orphans)) {
            $this->newLine();
            $this->warn('Verwaiste Module (nicht mehr im Katalog):');
            foreach ($orphans as $slug) {
                $this->warn("  • {$slug}");
            }
        }

        return 0;
    }
}
