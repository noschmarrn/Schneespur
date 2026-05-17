<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ModulesRemove extends Command
{
    protected $signature = 'schneespur:modules-remove
        {slug : The module slug to remove}
        {--force : Skip confirmation prompt}';

    protected $description = 'Remove an installed module completely.';

    public function handle(
        SchneespurModuleInstaller $installer,
        SchneespurModuleClient $client,
    ): int {
        if (! Schema::hasTable('modules')) {
            $this->error('Modules-Tabelle nicht vorhanden. Bitte zuerst "php artisan migrate" ausführen.');
            return 1;
        }

        $slug = $this->argument('slug');

        $module = Module::bySlug($slug)->first();

        if (! $module) {
            $this->error("Modul \"{$slug}\" nicht gefunden.");
            return 1;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Modul \"{$slug}\" (v{$module->version}) wirklich entfernen?")) {
                $this->info('Abgebrochen.');
                return 0;
            }
        }

        $module->update(['enabled' => false]);

        $removed = $installer->remove($slug);

        if (! $removed) {
            $this->warn("Modul-Dateien für \"{$slug}\" konnten nicht gelöscht werden (evtl. bereits entfernt).");
        }

        $module->delete();

        $state = $client->loadState();
        $state['installed'] = Module::pluck('slug')->toArray();
        $state['orphans'] = array_values(array_diff($state['orphans'] ?? [], [$slug]));
        $client->writeState($state);

        $this->info("Modul \"{$slug}\" wurde entfernt.");

        return 0;
    }
}
