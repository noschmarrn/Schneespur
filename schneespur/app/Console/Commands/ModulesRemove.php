<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\ModuleManager;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

        $migrationPath = "modules/{$slug}/database/migrations";
        $fullMigrationPath = base_path($migrationPath);

        if (File::isDirectory($fullMigrationPath) && ! empty(File::glob($fullMigrationPath . '/*.php'))) {
            try {
                Artisan::call('migrate:rollback', [
                    '--path' => $migrationPath,
                    '--force' => true,
                    '--step' => 999,
                ]);
                $this->info("Migrationen für \"{$slug}\" zurückgerollt.");
            } catch (\Throwable $e) {
                Log::warning("Module migration rollback failed for '{$slug}': {$e->getMessage()}");
                $this->warn("Migrations-Rollback für \"{$slug}\" fehlgeschlagen: {$e->getMessage()}");
            }
        }

        $deleted = app(ModuleManager::class)->cleanupSettings($slug);
        if ($deleted > 0) {
            $this->info("Einstellungen für \"{$slug}\" aufgeräumt ({$deleted} entfernt).");
        }

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
