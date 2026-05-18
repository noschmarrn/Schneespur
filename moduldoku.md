# Schneespur Modulsystem — Dokumentation

## Überblick

Das Schneespur-Modulsystem erlaubt es, die Kernfunktionalität über eigenständige, installierbare Module zu erweitern. Module können:

- **Admin-Navigation** erweitern (neue Menüpunkte, Gruppen)
- **Dashboard-Widgets** hinzufügen (Statistiken, Status-Karten)
- **Wetter-Provider** registrieren (eigene Wetterdatenquellen)
- **Events** abonnieren (auf Einsatzstart/-ende, Kundenanlage etc. reagieren)
- **Eigene Routes** definieren (Settings-Seiten, API-Endpoints)
- **Eigene Views** laden (Blade-Templates im Modul-Namespace)

Module werden über einen zentralen Katalog-Server bezogen, per SHA256 verifiziert und können über die Admin-Oberfläche oder CLI installiert, aktiviert, deaktiviert und entfernt werden.

---

## Ordnerstruktur eines Moduls

Module liegen unter `modules/<slug>/`. Beispiel:

```
modules/mein-modul/
├── module.json                         # Manifest (Pflicht)
├── src/                                # PHP-Quellcode (PSR-4)
│   ├── MeinModulServiceProvider.php    # ServiceProvider (Pflicht)
│   └── Http/
│       └── Controllers/
│           └── MeinController.php
├── resources/
│   └── views/                          # Blade-Templates
│       ├── settings.blade.php
│       └── widgets/
│           └── status-card.blade.php
├── database/
│   └── migrations/                     # Migrationen (optional)
└── README.md
```

### Minimalstruktur

Das absolute Minimum für ein funktionierendes Modul:

```
modules/mein-modul/
├── module.json
└── src/
    └── MeinModulServiceProvider.php
```

---

## module.json — Das Manifest

Jedes Modul benötigt eine `module.json` im Wurzelverzeichnis:

```json
{
    "name": "Mein Modul",
    "version": "1.0.0",
    "namespace": "Schneespur\\Module\\MeinModul",
    "service_provider": "Schneespur\\Module\\MeinModul\\MeinModulServiceProvider",
    "description": "Kurzbeschreibung dessen, was das Modul tut.",
    "min_schneespur_version": "1.0.0"
}
```

| Feld | Pflicht | Beschreibung |
|------|---------|-------------|
| `name` | Ja | Anzeigename des Moduls |
| `version` | Nein | Semantic Versioning (z.B. `1.2.3`) |
| `namespace` | Nein | PHP-Namespace für PSR-4-Autoloading (Basis für `src/`) |
| `service_provider` | Nein | Vollqualifizierter Klassenname des ServiceProviders |
| `description` | Nein | Kurzbeschreibung |
| `min_schneespur_version` | Nein | Mindestversion der Schneespur-Installation |

**Wichtig:** `name` ist das einzige wirklich Pflichtfeld. Ohne `service_provider` wird das Modul aber nicht gebootet (nur discovered).

### i18n im Manifest

Für den Katalog-Server können `name` und `description` auch als i18n-Objekt definiert sein:

```json
{
    "name": {"de": "Wetterstation", "en": "Weather Station"},
    "description": {"de": "Erweiterte Wetterdaten", "en": "Extended weather data"}
}
```

Die Locale-Auflösung folgt der Reihenfolge: aktuelle App-Locale → `primary_locale` → `de` → erster nicht-leerer Wert.

---

## Der ServiceProvider

Der ServiceProvider ist die zentrale Einstiegsklasse eines Moduls. Er muss `Illuminate\Support\ServiceProvider` erweitern und wird vom `ModuleManager` automatisch instanziiert und gebootet.

### Grundgerüst

```php
<?php

namespace Schneespur\Module\MeinModul;

use Illuminate\Support\ServiceProvider;

class MeinModulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings im Container registrieren (optional)
    }

    public function boot(): void
    {
        // Views, Navigation, Widgets, Events, Routes hier registrieren
    }
}
```

### Vollständiges Beispiel mit allen Extension Points

```php
<?php

namespace Schneespur\Module\MeinModul;

use App\Events\JobCompleted;
use App\Events\JobStarted;
use App\Services\Extension\DashboardWidgetRegistry;
use App\Services\Extension\NavigationRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MeinModulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 1. Blade-Views laden
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mein-modul');

        // 2. Navigation registrieren
        $this->registerNavigation();

        // 3. Dashboard-Widget registrieren
        $this->registerWidget();

        // 4. Event-Listener registrieren
        $this->registerEventListeners();

        // 5. Routes registrieren
        $this->registerRoutes();
    }

    protected function registerNavigation(): void
    {
        $nav = $this->app->make(NavigationRegistry::class);

        $nav->addItem(
            group: 'system',
            slug: 'mein-modul',
            label: 'Mein Modul',
            route: 'admin.mein-modul.settings',
            icon: 'heroicon-o-puzzle-piece',
            order: 200,
        );
    }

    protected function registerWidget(): void
    {
        $widgets = $this->app->make(DashboardWidgetRegistry::class);

        $widgets->registerWidget('mein-modul-status', [
            'label' => 'Mein Modul Status',
            'view' => 'mein-modul::widgets.status-card',
            'order' => 200,
            'size' => 'half',
        ]);
    }

    protected function registerEventListeners(): void
    {
        $this->app['events']->listen(JobCompleted::class, function (JobCompleted $event) {
            Log::info('MeinModul: Einsatz abgeschlossen', [
                'job_id' => $event->job->id,
            ]);
        });
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('admin/mein-modul')
            ->name('admin.mein-modul.')
            ->group(function () {
                Route::get('settings', [Http\Controllers\MeinController::class, 'index'])
                    ->name('settings');
            });
    }
}
```

---

## Extension Points im Detail

### 1. Navigation (NavigationRegistry)

Module können Menüpunkte in der Admin-Seitenleiste hinzufügen.

```php
$nav = $this->app->make(NavigationRegistry::class);

// Einzelnen Menüpunkt in bestehende Gruppe einfügen
$nav->addItem(
    group: 'system',              // Zielgruppe (siehe Gruppen-Liste unten)
    slug: 'mein-modul',           // Eindeutiger Bezeichner
    label: 'Mein Modul',          // Anzeige-Label
    route: 'admin.mein-modul.settings',  // Laravel-Route-Name
    icon: 'heroicon-o-cog-6-tooth',      // Heroicon-Bezeichner
    order: 200,                   // Sortierung (höher = weiter unten)
    permission: null,             // Optional: Berechtigungsprüfung
    routeCheck: null,             // Optional: Route-Existenzprüfung
    activePattern: 'admin.mein-modul.*',  // Optional: Aktiv-Markierung
    badge: null,                  // Optional: Badge-Variable
);

// Eigene Navigationsgruppe anlegen
$nav->addGroup(
    key: 'module-bereich',        // Gruppen-Schlüssel
    label: 'Erweiterungen',       // Gruppen-Label
    order: 90,                    // Position (höher = weiter unten)
);
```

**Verfügbare Core-Gruppen (nach Reihenfolge):**

| Gruppe | Label | Order | Beschreibung |
|--------|-------|-------|-------------|
| `top` | — | 10 | Dashboard |
| `stammdaten` | Stammdaten | 20 | Kunden, Fahrer, Fahrzeuge, Objekte |
| `einsaetze` | Einsätze | 30 | Jobs, Schichten |
| `auswertungen` | Auswertungen | 40 | Statistiken, Export |
| `system` | System | 50 | Einstellungen, Hilfe, Module |

**Empfehlung:** Module sollten `order: 200+` verwenden, um nach den Core-Einträgen zu erscheinen. Für eigene Gruppen `order: 90+` wählen.

**Duplikat-Verhalten:** Wird ein `slug` doppelt registriert, überschreibt der spätere Eintrag den früheren (Last-Wins-Semantik). Ein Warning wird geloggt.

### 2. Dashboard-Widgets (DashboardWidgetRegistry)

Module können Karten auf dem Admin-Dashboard anzeigen.

```php
$widgets = $this->app->make(DashboardWidgetRegistry::class);

$widgets->registerWidget('mein-widget', [
    'label' => 'Widget-Titel',
    'view' => 'mein-modul::widgets.status-card',  // Blade-View
    'order' => 200,        // Sortierung
    'size' => 'half',      // 'half' oder 'full' (halbe oder ganze Breite)

    // Optional: Daten für das View bereitstellen
    'dataCallback' => function () {
        return [
            'anzahl' => \App\Models\Job::count(),
            'letzer' => \App\Models\Job::latest()->first(),
        ];
    },

    // Optional: Widget nur unter bestimmten Bedingungen anzeigen
    'condition' => function () {
        return \App\Models\Setting::get('mein_modul_aktiv') === '1';
    },

    // Optional: Nur für bestimmte Berechtigungen sichtbar
    'permission' => null,
]);
```

**Widget-Konfiguration im Detail:**

| Key | Typ | Default | Beschreibung |
|-----|-----|---------|-------------|
| `label` | string | Slug | Anzeigename |
| `view` | string | null | Blade-View-Pfad (Namespace-Syntax) |
| `order` | int | 100 | Sortierreihenfolge |
| `size` | string | `'full'` | `'full'` (100% Breite) oder `'half'` (50%) |
| `dataCallback` | Closure\|null | null | Funktion, die Daten für das View liefert |
| `condition` | Closure\|null | null | Funktion, die `true`/`false` zurückgibt (Sichtbarkeit) |
| `permission` | string\|null | null | Berechtigungs-Gate |

**Fehlerbehandlung:** Wirft `dataCallback` eine Exception, wird das Widget trotzdem gerendert — mit `error: true` im Datenarray. Wirft `condition` eine Exception, wird das Widget übersprungen.

**Blade-View des Widgets:**

Das View erhält die Daten aus `dataCallback` als `$data`-Variable:

```blade
{{-- resources/views/widgets/status-card.blade.php --}}
<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
    <h3 class="text-sm font-medium text-gray-900">Mein Widget</h3>
    @if(isset($data['anzahl']))
        <p class="text-2xl font-bold">{{ $data['anzahl'] }}</p>
    @endif
</div>
```

### 3. Wetter-Provider (WeatherProviderRegistry)

Module können eigene Wetterdatenquellen registrieren.

**Schritt 1: Interface implementieren**

```php
<?php

namespace Schneespur\Module\MeinWetter;

use App\Services\Weather\WeatherData;
use App\Services\Weather\WeatherProviderInterface;

class MeinWetterProvider implements WeatherProviderInterface
{
    public function fetchCurrent(float $lat, float $lon): ?WeatherData
    {
        // API abrufen und WeatherData-Objekt zurückgeben
        return new WeatherData(
            temperature: -2.5,
            humidity: 85,
            windSpeed: 12.3,
            precipitation: 0.5,
            snowfall: 2.0,
            condition: 'Schneefall',
            cloudCover: 90,
            providerName: $this->name(),
        );
    }

    /**
     * Verbindungstest (wird in den Einstellungen aufgerufen).
     *
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function testConnection(float $lat, float $lon): array
    {
        $start = microtime(true);

        try {
            $result = $this->fetchCurrent($lat, $lon);
            $ms = (int) ((microtime(true) - $start) * 1000);

            return [
                'ok' => $result !== null,
                'message' => $result ? 'Verbindung erfolgreich' : 'Keine Daten',
                'latency_ms' => $ms,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            ];
        }
    }

    public function name(): string
    {
        return 'Mein Wetterdienst';
    }

    public function requiresApiKey(): bool
    {
        return true;
    }
}
```

**Schritt 2: Im ServiceProvider registrieren**

```php
use App\Services\Weather\WeatherProviderRegistry;

public function boot(): void
{
    $registry = $this->app->make(WeatherProviderRegistry::class);
    $registry->register('mein-wetter', MeinWetterProvider::class);
}
```

Der registrierte Provider erscheint automatisch in den Wetter-Einstellungen und kann vom Admin ausgewählt werden.

### 4. Events abonnieren

Module können auf Anwendungs-Events reagieren.

```php
use App\Events\JobCompleted;
use App\Events\JobStarted;
use App\Events\CustomerCreated;
use App\Events\WeatherSnapshotCreated;

public function boot(): void
{
    $events = $this->app['events'];

    // Einsatz gestartet
    $events->listen(JobStarted::class, function (JobStarted $event) {
        $job = $event->job;           // App\Models\Job
    });

    // Einsatz abgeschlossen
    $events->listen(JobCompleted::class, function (JobCompleted $event) {
        $job = $event->job;                       // App\Models\Job
        $wetterVerfuegbar = $event->weatherAvailable;   // bool
        $istWetterUpdate = $event->isWeatherUpdate;     // bool
    });

    // Neuer Kunde angelegt
    $events->listen(CustomerCreated::class, function (CustomerCreated $event) {
        $customer = $event->customer;  // App\Models\Customer
    });

    // Wetter-Snapshot erstellt
    $events->listen(WeatherSnapshotCreated::class, function (WeatherSnapshotCreated $event) {
        $snapshot = $event->snapshot;  // App\Models\WeatherSnapshot
    });
}
```

**Verfügbare Events:**

| Event | Properties | Wann ausgelöst |
|-------|-----------|----------------|
| `JobStarted` | `Job $job` | Einsatz begonnen |
| `JobCompleted` | `Job $job`, `bool $weatherAvailable`, `bool $isWeatherUpdate` | Einsatz abgeschlossen |
| `CustomerCreated` | `Customer $customer` | Neuer Kunde angelegt |
| `WeatherSnapshotCreated` | `WeatherSnapshot $snapshot` | Wetterdaten abgerufen |

### 5. Routes registrieren

Module können eigene Routes definieren.

```php
use Illuminate\Support\Facades\Route;

protected function registerRoutes(): void
{
    // Admin-Bereich (authentifiziert)
    Route::middleware(['web', 'auth'])
        ->prefix('admin/mein-modul')
        ->name('admin.mein-modul.')
        ->group(function () {
            Route::get('settings', [Http\Controllers\MeinController::class, 'index'])
                ->name('settings');
            Route::post('settings', [Http\Controllers\MeinController::class, 'store'])
                ->name('settings.store');
        });
}
```

**Middleware-Konventionen:**

| Kontext | Middleware | Prefix |
|---------|-----------|--------|
| Admin-Seiten | `['web', 'auth']` | `admin/mein-modul` |
| API-Endpoints | `['api']` | `api/mein-modul` |
| Öffentlich | `['web']` | Nach Bedarf |

**Empfehlung:** Für Admin-Routen immer `auth`-Middleware verwenden. Route-Namen mit `admin.mein-modul.` prefixen, damit die Navigation korrekt markiert wird.

### 6. Views laden

Module registrieren ihre Blade-Views unter einem Namespace:

```php
// Im ServiceProvider
$this->loadViewsFrom(__DIR__ . '/../resources/views', 'mein-modul');

// Nutzung in Routes oder Controllern
return view('mein-modul::settings', ['key' => 'value']);

// Widget-Views
'view' => 'mein-modul::widgets.status-card',
```

Der View-Namespace (`mein-modul`) isoliert die Templates vom Rest der Anwendung. In Blade-Templates können alle Schneespur-Layouts und Components verwendet werden:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mein Modul — Einstellungen
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Modul-Inhalte --}}
        </div>
    </div>
</x-app-layout>
```

---

## PSR-4 Autoloading

Der `ModuleManager` registriert automatisch einen PSR-4-Autoloader für jedes Modul, das einen `namespace` im Manifest definiert. Der Autoloader mappt den Namespace auf das `src/`-Verzeichnis:

```
Namespace: Schneespur\Module\MeinModul\
Basis:     modules/mein-modul/src/
```

**Beispiel-Mapping:**

| Klasse | Datei |
|--------|-------|
| `Schneespur\Module\MeinModul\MeinModulServiceProvider` | `modules/mein-modul/src/MeinModulServiceProvider.php` |
| `Schneespur\Module\MeinModul\Http\Controllers\MeinController` | `modules/mein-modul/src/Http/Controllers/MeinController.php` |
| `Schneespur\Module\MeinModul\Services\MeinService` | `modules/mein-modul/src/Services/MeinService.php` |

---

## Modul-Lebenszyklus

### Boot-Reihenfolge

1. **Laravel bootet** → `AppServiceProvider::register()` registriert `ModuleManager` als Singleton
2. **`app()->booted()`** → `ModuleManager::boot()` wird aufgerufen
3. **Discovery** → `modules/*/module.json` wird gescannt
4. **Pro aktivem Modul:**
   - PSR-4-Autoloader wird registriert (wenn `namespace` + `src/` vorhanden)
   - ServiceProvider-Klasse wird instanziiert
   - `register()` wird aufgerufen (Container-Bindings)
   - `boot()` wird aufgerufen (Views, Routes, Navigation etc.)
5. **Bei Fehler** → Modul wird automatisch deaktiviert, Error wird geloggt

### Installation (via Admin-UI oder CLI)

1. Katalog wird vom Server abgerufen
2. ZIP wird heruntergeladen
3. Dateigröße und SHA256-Hash werden verifiziert
4. ZIP wird nach `modules/<slug>/` extrahiert
5. Datenbank-Eintrag wird erstellt (`modules`-Tabelle)
6. Beim nächsten Request wird das Modul discovered und gebootet

### Update

1. Aktuelles Modul-Verzeichnis wird als Backup gesichert (`modules/<slug>.bak/`)
2. Neue ZIP wird heruntergeladen und verifiziert
3. ZIP wird extrahiert
4. Bei Erfolg: Backup wird gelöscht
5. Bei Fehler: Automatisches Rollback aus Backup

### Deaktivierung

- `Module.enabled = false` in der Datenbank
- Der `ModuleManager` überspringt deaktivierte Module beim Boot
- Kein Neustart nötig — wirkt ab dem nächsten Request

### Entfernung

1. Modul wird in der Datenbank deaktiviert
2. Verzeichnis `modules/<slug>/` wird gelöscht
3. Datenbank-Eintrag wird gelöscht

---

## Fehlerbehandlung und Sicherheit

### Automatische Deaktivierung

Wenn ein Modul beim Booten fehlschlägt, wird es automatisch deaktiviert:

- ServiceProvider-Klasse nicht gefunden → Auto-Disable + Log
- Klasse ist kein ServiceProvider → Auto-Disable + Log
- Exception in `register()` oder `boot()` → Auto-Disable + Stacktrace im Log

Die Anwendung stürzt nie wegen eines fehlerhaften Moduls ab.

### Sicherheitsmaßnahmen

| Maßnahme | Beschreibung |
|----------|-------------|
| SHA256-Verifikation | Downloads werden per SHA256-Hash geprüft (`hash_equals()`, Timing-safe) |
| HTTPS-Only | Download-URLs müssen HTTPS verwenden |
| Slug-Validierung | Nur `[a-z0-9_-]` erlaubt — verhindert Path-Traversal |
| ZIP-Prüfung | ZIP-Einträge mit `..` oder absoluten Pfaden werden abgelehnt |
| Atomare State-Datei | State-Datei wird via temp-File + Rename geschrieben (kein Korruptionsrisiko) |

---

## CLI-Befehle

### Module synchronisieren

```bash
php artisan schneespur:modules-sync
php artisan schneespur:modules-sync --dry-run   # Nur anzeigen, nicht installieren
```

Synchronisiert den lokalen Bestand mit dem Katalog-Server. Installiert neue Module, aktualisiert vorhandene (bei geändertem SHA256), überspringt identische.

### Module auflisten

```bash
php artisan schneespur:modules-list
```

Zeigt alle installierten Module mit Version, Status (aktiviert/deaktiviert) und Installationszeitpunkt.

### Modul entfernen

```bash
php artisan schneespur:modules-remove mein-modul
php artisan schneespur:modules-remove mein-modul --force   # Ohne Bestätigung
```

Deaktiviert das Modul, löscht das Verzeichnis und den Datenbank-Eintrag.

---

## Admin-Oberfläche

Die Modulverwaltung ist erreichbar unter **Einstellungen → Module** (`/admin/settings/modules`).

**Funktionen:**

- **Verfügbare Module** aus dem Katalog anzeigen und installieren
- **Installierte Module** aktivieren, deaktivieren, aktualisieren oder entfernen
- **Update-Hinweis** bei verfügbaren neuen Versionen
- **Orphan-Warnung** bei lokal installierten Modulen, die nicht mehr im Katalog sind

---

## Datenbank-Schema

### Tabelle: `modules`

| Spalte | Typ | Beschreibung |
|--------|-----|-------------|
| `id` | bigint, auto-increment | Primärschlüssel |
| `slug` | varchar(128), unique | Modul-Bezeichner |
| `version` | varchar(64) | Installierte Version |
| `enabled` | boolean | Ob das Modul gebootet wird |
| `manifest_json` | json | Katalog-Eintrag als JSON |
| `installed_at` | timestamp, nullable | Installationszeitpunkt |
| `created_at` | timestamp | Erstellungszeitpunkt |
| `updated_at` | timestamp | Letztes Update |

---

## Konfiguration

Datei: `config/schneespur_modules.php`

```php
return [
    'server_url'       => 'https://jenni.noschmarrn.dev',
    'collection_slug'  => 'schneespur-module',
    'catalog_endpoint' => '/api/modules/{slug}',
    'timeout'          => 10,       // Katalog-Abruf (Sekunden)
    'download_timeout' => 120,      // ZIP-Download (Sekunden)
    'state_file_path'  => storage_path('app/schneespur_modules_state.json'),
    'modules_path'     => base_path('modules'),
];
```

---

## Referenz-Modul

Das Verzeichnis `modules/example/` enthält ein vollständiges Referenz-Modul, das alle Extension Points demonstriert:

- Navigation (Menüpunkt unter "System")
- Dashboard-Widget (halbe Breite, Status-Karte)
- Event-Listener (loggt `JobCompleted`)
- Settings-Seite (eigene Route + View)

**Tipp:** Am besten das Referenz-Modul als Basis kopieren und anpassen:

```bash
cp -r modules/example modules/mein-modul
```

Dann `module.json`, Namespace und Klassennamen anpassen.

---

## Checkliste: Neues Modul erstellen

1. [ ] Ordner `modules/<slug>/` anlegen
2. [ ] `module.json` mit Name, Version, Namespace und ServiceProvider erstellen
3. [ ] `src/<Name>ServiceProvider.php` erstellen, `Illuminate\Support\ServiceProvider` erweitern
4. [ ] In `boot()` die gewünschten Extension Points registrieren:
   - Navigation → `NavigationRegistry::addItem()`
   - Widget → `DashboardWidgetRegistry::registerWidget()`
   - Wetter → `WeatherProviderRegistry::register()`
   - Events → `$this->app['events']->listen()`
   - Routes → `Route::middleware(...)->group(...)`
   - Views → `$this->loadViewsFrom(...)`
5. [ ] Views unter `resources/views/` ablegen
6. [ ] Testen: Modul aktivieren, Schneespur aufrufen, Menü und Dashboard prüfen

---

## Diagnose-Grundgerüst (Diagnostic Infrastructure)

Schneespur stellt ein neutrales, anbieterneutrales Diagnose-Grundgerüst im Core bereit. Es ermöglicht Modulen, eigene Diagnose- und Reporting-Lösungen sauber zu integrieren, ohne dass der Core an einen bestimmten Anbieter gekoppelt ist.

### Übersicht

Das Grundgerüst besteht aus vier Komponenten:

| Komponente | Klasse | Aufgabe |
|------------|--------|---------|
| Interface | `DiagnosticReporterInterface` | Vertrag für Diagnose-Reporter |
| Registry | `DiagnosticReporterRegistry` | Reporter per Slug registrieren und abrufen |
| Manager | `DiagnosticManager` | Zentraler Service für Diagnose-Meldungen |
| Sanitizer | `DiagnosticPayloadSanitizer` | Personenbezogene Daten aus Payloads entfernen |

### DiagnosticReporterInterface

Jedes Diagnose-Modul implementiert dieses Interface:

```php
<?php

namespace App\Services\Diagnostic;

interface DiagnosticReporterInterface
{
    /**
     * Diagnose-Ereignis melden.
     *
     * @param  string  $type     Ereignis-Typ (z.B. 'exception', 'cron_failed')
     * @param  array   $payload  Bereits gesäuberte Ereignis-Daten
     * @param  array   $context  System-Kontext (Version, PHP, Module, Route etc.)
     */
    public function report(string $type, array $payload = [], array $context = []): void;

    /**
     * Ob der Reporter aktiv ist (z.B. konfiguriert und freigeschaltet).
     */
    public function isEnabled(): bool;

    /**
     * Verbindungstest.
     *
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function testConnection(): array;
}
```

**Unterstützte Ereignis-Typen (Beispiele):**

| Typ | Beschreibung |
|-----|-------------|
| `exception` | Unbehandelte Laravel-Exception (automatisch via Exception-Hook) |
| `cron_failed` | Fehlgeschlagener Cronjob/Artisan-Befehl |
| `weather_provider_failed` | Fehler beim Wetterdaten-Abruf |
| `module_boot_failed` | Modul konnte nicht gestartet werden |
| `module_install_failed` | Modul-Installation fehlgeschlagen |
| `module_update_failed` | Modul-Update fehlgeschlagen |
| `update_failed` | Schneespur-Update fehlgeschlagen |
| `performance_warning` | Performance-Warnung |
| `custom` | Freier Typ für modulspezifische Ereignisse |

### DiagnosticReporterRegistry

Reporter werden per Slug registriert, analog zu allen anderen Schneespur-Registries:

```php
use App\Services\Diagnostic\DiagnosticReporterRegistry;

public function boot(): void
{
    $registry = $this->app->make(DiagnosticReporterRegistry::class);
    $registry->register('mein-reporter', MeinReporter::class);
}
```

**Eigenschaften:**

- Singleton im Container
- Mehrere Reporter gleichzeitig möglich
- Duplikate werden mit Warning geloggt (Last-Wins-Semantik)
- `enabledReporters()` gibt nur aktive Reporter zurück (`isEnabled() === true`)

### DiagnosticManager

Zentraler Service, über den Core und Module Diagnose-Ereignisse melden:

```php
use App\Services\Diagnostic\DiagnosticManager;

// Allgemeine Meldung
app(DiagnosticManager::class)->report('cron_failed', [
    'command' => 'schneespur:weather-refresh',
    'exit_code' => 1,
]);

// Exception melden (inkl. Trace)
app(DiagnosticManager::class)->reportException($exception);

// Exception ohne Trace
app(DiagnosticManager::class)->reportException($exception, [], false);
```

**Verhalten:**

- Ruft alle aktivierten Reporter auf
- **Isoliert Reporter-Fehler**: Wenn ein Reporter crasht, wird der Fehler geloggt, aber Schneespur läuft weiter
- **Reentrance-Schutz**: Verhindert Endlos-Schleifen, wenn ein Reporter selbst eine Exception wirft
- Alle Payloads werden automatisch durch den `DiagnosticPayloadSanitizer` bereinigt
- System-Kontext (Version, PHP, Laravel, aktive Module, Route) wird automatisch hinzugefügt

### Automatischer Exception-Hook

Der Core registriert einen Exception-Hook in `bootstrap/app.php`, der **alle** Laravel-Exceptions automatisch an den `DiagnosticManager` weiterreicht:

- HTTP 500-Fehler
- Controller-Exceptions
- Job/Queue-Exceptions
- Artisan/Command-Exceptions
- PDF-Erstellungsfehler
- Jede andere `\Throwable` in Laravel

Der Hook ist nur aktiv, wenn mindestens ein Reporter registriert und aktiviert ist. Ohne konfiguriertes Diagnose-Modul hat der Hook keinen Effekt.

```php
// bootstrap/app.php — automatisch vom Core registriert
$exceptions->reportable(function (\Throwable $e) {
    $manager = app(DiagnosticManager::class);
    if ($manager->hasEnabledReporters()) {
        $manager->reportException($e);
    }
    return false; // Laravel-Standard-Logging bleibt erhalten
});
```

**Wichtig:** `return false` bedeutet, dass der Hook das Standard-Laravel-Logging nicht ersetzt, sondern ergänzt.

### DiagnosticPayloadSanitizer — Datenschutz

Alle Diagnose-Payloads werden vor dem Versand automatisch bereinigt.

**Wird entfernt/anonymisiert:**

| Kategorie | Beispiele |
|-----------|----------|
| Zugangsdaten | Passwörter, Tokens, API-Keys, Secrets, DSN |
| HTTP-Header | Authorization, Cookie, Session |
| CSRF | `_token`, CSRF-Tokens |
| Personenbezogen | E-Mail-Adressen, IP-Adressen |
| Session-Daten | Session-IDs, Session-Inhalte |

**Erlaubt als technische Diagnose:**

| Datum | Beschreibung |
|-------|-------------|
| Fehlerklasse | z.B. `RuntimeException` |
| Fehlermeldung | Gekürzt auf 500 Zeichen, E-Mails/IPs entfernt |
| Datei + Zeile | Relative Pfade (ohne Base-Path) |
| Stacktrace | Optional, max. 30 Frames, nur Datei/Zeile/Funktion |
| Schneespur-Version | Aus `VERSION`-Datei |
| PHP-/Laravel-Version | Laufzeit-Information |
| Aktive Module | Slugs der aktiven Module |
| Route | Ohne Query-Parameter |
| Kanal | `http` oder `cli` |

### module.json — `requires_permissions`

Module können im Manifest deklarieren, welche Diagnose-Berechtigungen sie benötigen:

```json
{
    "name": "Mein Diagnose-Modul",
    "version": "1.0.0",
    "namespace": "Schneespur\\Module\\MeinDiagnose",
    "service_provider": "Schneespur\\Module\\MeinDiagnose\\MeinDiagnoseServiceProvider",
    "description": "Fehlerreporting an externen Service.",
    "min_schneespur_version": "1.0.0",
    "requires_permissions": [
        "diagnostics.report",
        "diagnostics.report_exceptions",
        "diagnostics.read_environment",
        "diagnostics.send_outbound_http"
    ]
}
```

**Definierte Berechtigungen:**

| Berechtigung | Bedeutung |
|-------------|-----------|
| `diagnostics.report` | Darf `DiagnosticManager::report()` nutzen |
| `diagnostics.report_exceptions` | Darf Exception-Daten erhalten |
| `diagnostics.read_environment` | Darf System-Kontext (PHP-Version, Module etc.) lesen |
| `diagnostics.send_outbound_http` | Darf HTTP-Requests an externe Server senden |

Die deklarierten Berechtigungen werden in der Admin-Modulverwaltung angezeigt. Eine aktive Durchsetzung ist für spätere Versionen vorgesehen.

### Core-Meldepunkte

Der Core meldet automatisch folgende Diagnose-Ereignisse:

| Meldepunkt | Typ | Payload |
|------------|-----|---------|
| Modul-Boot fehlgeschlagen | `module_boot_failed` | `module_slug`, `class`, `message`, `file`, `line` |
| Modul-Installation fehlgeschlagen | `module_install_failed` | `module_slug`, `reason` |
| Modul-Update fehlgeschlagen | `module_update_failed` | `module_slug`, `reason` |
| Alle Laravel-Exceptions | `exception` | `class`, `message`, `code`, `file`, `line`, `trace` |

### Beispiel: Diagnose-Modul erstellen

Ein minimales Diagnose-Modul, das Ereignisse loggt:

```php
<?php

namespace Schneespur\Module\MeinDiagnose;

use App\Services\Diagnostic\DiagnosticReporterInterface;
use App\Models\Setting;

class MeinReporter implements DiagnosticReporterInterface
{
    public function report(string $type, array $payload = [], array $context = []): void
    {
        // Hier eigene Logik: HTTP-Request, Datenbank, Datei, etc.
        // $payload und $context sind bereits gesäubert.
    }

    public function isEnabled(): bool
    {
        return Setting::get('mein_diagnose_enabled') === '1';
    }

    public function testConnection(): array
    {
        $start = microtime(true);

        // Verbindungstest-Logik hier
        $ok = true;

        return [
            'ok' => $ok,
            'message' => $ok ? 'Verbindung erfolgreich' : 'Fehler',
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
        ];
    }
}
```

```php
<?php

namespace Schneespur\Module\MeinDiagnose;

use App\Services\Diagnostic\DiagnosticReporterRegistry;
use Illuminate\Support\ServiceProvider;

class MeinDiagnoseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(DiagnosticReporterRegistry::class);
        $registry->register('mein-diagnose', MeinReporter::class);
    }
}
```

### Best Practices für externe Reporter

1. **Keine ungesäuberten Daten versenden** — der `DiagnosticPayloadSanitizer` bereinigt automatisch, aber vermeiden Sie es, Roh-Daten am Manager vorbei zu versenden.
2. **Verbindungsfehler abfangen** — `report()` darf keine Exceptions werfen. Der Manager fängt sie ab, aber ein sauberer try/catch im Reporter ist besser.
3. **`isEnabled()` korrekt implementieren** — prüfen Sie, ob alle nötigen Konfigurationswerte (Endpoint, Credentials etc.) vorhanden sind.
4. **`testConnection()` bereitstellen** — ermöglicht dem Admin, die Verbindung in den Einstellungen zu testen.
5. **Rate-Limiting bedenken** — bei hohem Fehleraufkommen kann ein Reporter überlastet werden. Implementieren Sie ggf. lokales Rate-Limiting.
6. **Queue-Verarbeitung vorbereiten** — für asynchrones Reporting kann der Reporter intern Laravel-Jobs dispatchen.
7. **Keine feste Kopplung** — der Reporter sollte konfigurierbar sein (Endpoint, Credentials über Settings), nicht hardcodiert.
8. **`requires_permissions` deklarieren** — im `module.json` die benötigten Berechtigungen auflisten.

---

## Zusammenfassung der Extension-Registries

| Registry | Klasse | Methode | Beschreibung |
|----------|--------|---------|-------------|
| Navigation | `NavigationRegistry` | `addItem(...)` | Menüpunkt zur Admin-Sidebar hinzufügen |
| Navigation | `NavigationRegistry` | `addGroup(...)` | Eigene Navigations-Gruppe erstellen |
| Dashboard | `DashboardWidgetRegistry` | `registerWidget(...)` | Widget auf dem Dashboard anzeigen |
| Wetter | `WeatherProviderRegistry` | `register(slug, class)` | Wetter-Provider registrieren |
| Diagnose | `DiagnosticReporterRegistry` | `register(slug, class)` | Diagnose-Reporter registrieren |

Alle Registries sind Singletons und werden über `$this->app->make(RegistryKlasse::class)` bezogen. Duplikate überschreiben mit Warning-Log (Last-Wins-Semantik).
