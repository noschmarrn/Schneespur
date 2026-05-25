<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\ModuleSignatureVerifier;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ModulesSync extends Command
{
    protected $signature = 'schneespur:modules-sync
        {--dry-run : Show what would happen without making changes}';

    protected $description = 'Sync modules from the catalog server (install/update/skip).';

    public function handle(
        SchneespurModuleClient $client,
        SchneespurModuleInstaller $installer,
        ModuleSignatureVerifier $verifier,
    ): int {
        if (! Schema::hasTable('modules')) {
            $this->error('Modules-Tabelle nicht vorhanden. Bitte zuerst "php artisan migrate" ausführen.');
            return 1;
        }

        $dryRun = $this->option('dry-run');
        $appVersion = config('app.version', '0.0.0');

        if ($dryRun) {
            $this->info('[DRY-RUN] Keine Änderungen werden vorgenommen.');
        }

        $this->info('Katalog wird abgerufen…');

        try {
            $catalog = $client->fetchCatalog();
        } catch (\Throwable $e) {
            $this->error('Katalog-Fetch fehlgeschlagen: ' . $e->getMessage());
            return 1;
        }

        if ($catalog === null) {
            $this->info('Katalog nicht geändert (304). Nichts zu tun.');
            return 0;
        }

        try {
            $verifier->refreshTrust();
        } catch (\RuntimeException $e) {
            $this->error('Trust-Refresh fehlgeschlagen: ' . $e->getMessage());
            return 1;
        }

        $modules = $catalog['modules'] ?? [];
        $catalogSlugs = [];
        $installed = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($modules as $entry) {
            $slug = $entry['slug'] ?? null;
            if (! $slug) {
                continue;
            }

            $catalogSlugs[] = $slug;
            $version = $entry['version'] ?? 'unknown';
            $sha256 = $entry['sha256'] ?? null;
            $size = $entry['size'] ?? null;
            $downloadUrl = $entry['download_url'] ?? null;
            $minAppVersion = $entry['minimum_app_version'] ?? null;

            if ($minAppVersion && version_compare($appVersion, $minAppVersion, '<')) {
                $this->warn("Modul {$slug} benötigt Schneespur >= {$minAppVersion}, aktuell {$appVersion} — übersprungen.");
                $skipped++;
                continue;
            }

            if (! $sha256 || ! $downloadUrl || ! $size) {
                $this->warn("Modul {$slug}: Fehlende Metadaten (sha256/download_url/size) — übersprungen.");
                $skipped++;
                continue;
            }

            $sigResult = $verifier->verifyModuleManifest($entry);
            if (! $sigResult->isAllowed) {
                $this->error("Modul {$slug}: Signaturprüfung fehlgeschlagen — {$sigResult->message}");
                $skipped++;
                continue;
            }
            if ($sigResult->status === 'unsigned') {
                $this->warn("Modul {$slug}: nicht signiert — wird mit Warnung installiert.");
            } else {
                $this->info("  Signatur verifiziert für {$slug} (key: {$sigResult->keyId}).");
            }

            $existing = Module::bySlug($slug)->first();

            if ($existing) {
                $existingManifest = $existing->manifest_json ?? [];
                $existingSha = $existingManifest['sha256'] ?? null;

                if ($existingSha === $sha256) {
                    $this->line("  {$slug} v{$version} — aktuell, übersprungen.");
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->info("[DRY-RUN] Würde aktualisieren: {$slug} → v{$version}");
                    $migPath = base_path("modules/{$slug}/database/migrations");
                    if (File::isDirectory($migPath) && ! empty(File::glob($migPath . '/*.php'))) {
                        $this->info("[DRY-RUN] Würde Migrationen ausführen für {$slug}");
                    }
                    $updated++;
                    continue;
                }

                $this->info("Aktualisiere {$slug} → v{$version}…");

                try {
                    $zipPath = $client->downloadModule($slug, $downloadUrl, $sha256, $size);
                    $success = $installer->update($zipPath, $slug);
                    @unlink($zipPath);

                    if (! $success) {
                        $this->error("Update fehlgeschlagen für {$slug}.");
                        continue;
                    }

                    $existing->update([
                        'version' => $version,
                        'manifest_json' => $entry,
                        'signature_status' => $sigResult->status,
                    ]);

                    $this->runModuleMigrations($slug);

                    $updated++;
                    $this->info("  ✓ {$slug} aktualisiert auf v{$version}.");
                } catch (\Throwable $e) {
                    $this->error("Fehler bei {$slug}: " . $e->getMessage());
                }
            } else {
                if ($dryRun) {
                    $this->info("[DRY-RUN] Würde installieren: {$slug} v{$version}");
                    $installed++;
                    continue;
                }


                $this->info("Installiere {$slug} v{$version}…");

                try {
                    $zipPath = $client->downloadModule($slug, $downloadUrl, $sha256, $size);
                    $success = $installer->install($zipPath, $slug);
                    @unlink($zipPath);

                    if (! $success) {
                        $this->error("Installation fehlgeschlagen für {$slug}.");
                        continue;
                    }

                    $newModule = Module::create([
                        'slug' => $slug,
                        'version' => $version,
                        'enabled' => true,
                        'manifest_json' => $entry,
                        'signature_status' => $sigResult->status,
                        'installed_at' => now(),
                    ]);

                    try {
                        $this->runModuleMigrations($slug);
                    } catch (\Throwable $migError) {
                        Log::error("Module migration failed during sync install of '{$slug}': {$migError->getMessage()}");
                        $this->error("Migration fehlgeschlagen für {$slug}: {$migError->getMessage()}");
                        $installer->remove($slug);
                        $newModule->delete();
                        continue;
                    }

                    $installed++;
                    $this->info("  ✓ {$slug} v{$version} installiert.");
                } catch (\Throwable $e) {
                    $this->error("Fehler bei {$slug}: " . $e->getMessage());
                }
            }
        }

        $this->detectOrphans($catalogSlugs, $client);

        $this->newLine();
        $this->info("Sync abgeschlossen: {$installed} installiert, {$updated} aktualisiert, {$skipped} übersprungen.");

        if (! $dryRun) {
            $state = $client->loadState();
            $state['installed'] = Module::pluck('slug')->toArray();
            $client->writeState($state);
        }

        return 0;
    }

    private function runModuleMigrations(string $slug): void
    {
        $migrationPath = "modules/{$slug}/database/migrations";
        $fullPath = base_path($migrationPath);

        if (! File::isDirectory($fullPath) || empty(File::glob($fullPath . '/*.php'))) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $migrationPath,
            '--force' => true,
        ]);

        $this->info("  Migrationen ausgeführt für {$slug}.");
    }

    private function detectOrphans(array $catalogSlugs, SchneespurModuleClient $client): void
    {
        $localSlugs = Module::pluck('slug')->toArray();
        $orphans = array_diff($localSlugs, $catalogSlugs);

        if (empty($orphans)) {
            return;
        }

        $this->warn('Verwaiste Module (lokal installiert, nicht mehr im Katalog):');
        foreach ($orphans as $slug) {
            $this->warn("  • {$slug}");
        }

        $state = $client->loadState();
        $state['orphans'] = array_values($orphans);
        $client->writeState($state);
    }
}
