# Changelog

All notable changes to **Wintertrace / Schneespur** are documented here. The same releases are published as [GitHub Releases](https://github.com/noschmarrn/Schneespur/releases) and offered in-app through the self-updater.

This project follows [Semantic Versioning](https://semver.org/). Unless noted, releases are backward-compatible and need no manual steps — just install the update.

---

## [Unreleased]

### Changed
- **The system requirement is stated as PHP 8.4.** It always was that in practice: every release since 1.1.7 shipped a dependency set that refuses to start below PHP 8.4.1, while the README, both install guides and the in-app help promised 8.2. On 8.2 or 8.3 that combination produces a blank error page on every request, with nothing explaining why — the check that produces it runs before the application starts, so the setup wizard never gets the chance to name the problem. The requirement is now stated correctly everywhere, including the wizard's own check, and the dependency resolver is pinned to 8.4.1 so that building on a machine with a newer PHP cannot raise the bar unnoticed a second time. Nothing changes for an installation that is running: if yours works, it is already on 8.4 or newer.

  Lowering the requirement instead was weighed and left for later. PHP 8.3 is within reach but costs around forty dependency updates including a Laravel minor version, and 8.2 is out of reach altogether because the image library requires 8.3 or newer.

## [1.2.0] — 2026-08-09 — Security hardening, regional formats & offline reliability

Three strands in one release.

**Hardening.** Two external penetration tests on 2026-08-03 — one against the 1.1.9 source, one against a live throwaway demo — produced seven distinct findings after verification. All seven are fixed here, and each one has a test that fails if it ever comes back.

**Units and formats.** Dates, times, numbers, currency, distances, temperatures and weights are no longer German-only. One settings page decides them for the whole installation, and every screen, report, export and email follows it.

**The driver app offline.** Actions that failed to sync are no longer thrown away silently, and the service worker now actually covers the driver pages — until this release it did not, so nothing was genuinely available offline.

Install this update. Nothing needs configuring afterwards — the format settings start from your installation language, and you can adjust them at any time. Do read the module note at the end if you run the Telegram or Dokumente module.

### Added
- **Units and formats follow your region.** A new settings page, *Admin → Settings → Units & Formats*, sets the currency and where its symbol goes, the decimal and thousands separators, the date and time format (including 12-hour time), and whether distances, temperatures and weights are metric or imperial. Region templates for Germany, Austria, Switzerland, the United States, Canada and the United Kingdom fill in all of it at once, and every field stays individually adjustable afterwards — Canada uses Canadian dollars but stays metric, the UK drives in miles but weighs in kilograms.

  The setting reaches everywhere the old hardcoded German formats used to: the admin interface, the driver app, the customer portal, the PDF reports, the CSV and Excel exports, and outgoing email. Speed follows the distance unit, as do snow depth and precipitation. Nothing is converted in storage — the same numbers are simply shown the way your region writes them, so switching back and forth is lossless. Both new and existing installations are seeded from the installation language — German installations get the German template, every other language starts on the United States one — and nothing that an operator has already set is ever overwritten.
- **Failed offline actions are kept and shown instead of being dropped.** When the driver app is out of network it queues what the driver does and replays it once there is signal again. An entry that still failed after three attempts used to be discarded without a word — the driver had no way to know something had been lost. Failed entries now stay in the queue, the connectivity indicator turns red and names how many there are, and a tap retries them. The count survives a page change and a reload, and it is read back from the queue itself rather than from the last event that happened to arrive.

### Fixed
- **Setup steps could still be reached after installation.** On an installed system, parts of the setup wizard remained callable without logging in, which allowed the mail configuration to be rewritten and a scheduled-task run to be triggered. Every setup route now refuses to work once the system is installed, and "installed" is decided the same way everywhere — a finished installation or an existing user account, so a deleted lock file changes nothing.
- **Text with links could carry active content.** Privacy notices are written in Markdown and rendered in four places (saving, preview, driver onboarding, and the frozen copy stored with each confirmation). The renderer accepted link targets that browsers treat as script. All four paths now go through one renderer that permits ordinary link schemes only.
- **A disabled module could still run.** Switching a module off was only remembered for the current request; after the next restart it booted again, routes and all. The switch is now read from the database at boot, and — this is the deliberate tightening — a module without a database entry does not boot at all. See the note below if you develop modules locally.
- **Photos were reachable by anyone who knew or guessed the address.** Uploaded files were stored in a publicly served folder. They are now kept outside the web root and delivered through a checked route: administrators see everything, drivers see the jobs they drove themselves, customers see their own jobs and only while photo sharing is enabled for them, and nobody else sees anything. Links are additionally signed and expire after 24 hours, so a forwarded address stops working. Existing files are moved to the protected location automatically in the background, in small batches, and remain available throughout — no downtime and no action needed.
- **A manual job could be booked onto someone who is not a driver.** The check consulted an obsolete field that has not been maintained since roles were reworked and that defaulted to "driver" for everyone. It now checks the actual role assignment.
- **The weather service contact address could contain line breaks.** It is sent as part of an outgoing request header, where line breaks have structural meaning. They are now removed both when saving and immediately before the request. Side effect worth knowing: a value that had picked up a line break previously made weather retrieval fail silently — that now works again.
- **Any signed-in user could reach the module interface.** The browser-session shortcut into the module API existed for administrators testing a module and admitted every logged-in account, including drivers. It is now restricted to administrators; module access tokens are unaffected.

Five more, found by installing this release on ordinary shared hosting rather than by running the test suite:

- **The driver app was never actually available offline.** The service worker — the piece that makes the app work without network — was installed from a subdirectory, and a service worker can only take charge of addresses below wherever it is served from. It therefore took charge of nothing the driver ever visits. Everything downstream of that was dead too: reloading a driver page without network showed the browser's connection error, and none of the caching rules for driver pages, fonts or module screens ever applied. The worker is now served from the top level, where it covers the whole app. After the update, open the driver app once with network available so the worker can install itself.
- **A fresh installation refused every request, the setup wizard included.** With no configuration file written yet, the host-header check introduced in 1.1.6 trusted only `localhost` — so on a real domain every single page answered with an error and there was no way in at all. The check now stays off until an installation has a configured address of its own to compare against. Existing installations were never affected.
- **The release package contained a database.** The build copied the whole database directory, developer scratch file included. A fresh installation connected to that file, found an account in it and concluded it was already installed, so the setup wizard refused to start. The release now ships the directory structure without any database.
- **The driver dashboard was also served at an address that had no sync queue.** Reaching it through `/dashboard` instead of `/driver` produced a page that looked identical but carried none of the offline machinery: anything done there while out of network was lost without a trace. That address now sends drivers to the real driver app.
- **The dashboard ran its own startup routine twice** on every load — two status requests, doubled online/offline listeners, and a timer left running for the life of the page.

### Security
- **PDF library updated** (dompdf 3.1.6) — closes six advisories — and the HTTP library updated alongside it. `composer audit` is clean.
- **PDF generation no longer fetches remote content.** Nothing in the reports needs it: photos are embedded directly, the logo is read from disk, the GPS track is drawn inline. It was switched on regardless, which is what made several of the advisories above applicable in the first place. Reports look exactly the same.

### Internal (for module developers)
The module documentation has been updated for everything below.

**Three hardening changes are breaking or near-breaking:**

- **Stored files are private by default.** The local storage backend writes outside the web root, and `url()` now returns a signed, permission-checked link rather than a permanent public one. Every module that stores files **must** register its path prefix with the new `MediaAccessRegistry` — an unregistered prefix is served to nobody and migrated for nobody. Never hand such a link to a third-party service that fetches it server-side; read the bytes and send those.
- **PDF hooks cannot load remote images.** `data:` URIs and local file paths still work.
- **The module API's session fallback is administrators only.** Token-based access is unchanged.
- A hand-copied module directory no longer boots, and no longer appears in the admin interface either, because a module now needs a database entry. Local module development therefore needs that entry created manually; a registration command is planned.

**New seams**, all additive and dormant until a module uses them:

- **`Formatter` and `RegionRegistry`.** Every display value now goes through one `Formatter` service — dates, times, numbers, money, distance, speed, temperature, weight — and modules must use it too, or their screens will contradict the rest of the page. It works outside Blade, so PDF, export and mail code can call it directly. `RegionRegistry` takes further region presets the same way `LocaleRegistry` takes languages. Note that `Formatter` is a container singleton: a test that changes a format setting has to forget the instance. New document: *Formats & Units*.
- **`MailableRegistry`** lets a module register its own mailable per notification type. Previously a module that declared any notification type had all of them sent as the job-completion email — the wrong template, to the wrong recipients. A registry entry that is not callable now fails loudly at registration instead of at send time.
- **Lifecycle handlers can declare that their failure must abort.** A handler registered as critical stops the driver's lifecycle transition when it throws, rather than letting the transition commit with the module's own work missing. The driver sees a normal error message; the exception is wrapped so it does not surface as a server error.
- **Admin form hooks on the customer-object and driver forms** let a module attach its own fields, with validation and persistence, the way `LifecycleFieldRegistry` already does for the driver screens. On a create form the hook receives `null` for the entity — not an unsaved model — so `$entity === null` is a reliable "this does not exist yet".
- **The offline queue exposes a failed state.** Actions a module queues through the driver app follow the same three-attempt rule and then surface in the driver's failed count rather than disappearing.
- **Module assets are cached for offline use.** Files served from `public/modules/{slug}/` are covered by a service-worker rule, so a module's driver screen survives without network. Filenames there are hashed and treated as immutable.

### Known limitations with existing modules
Two modules need their own update to work fully with 1.2.0. Both updates are prepared separately:

- **Telegram:** job photos are sent to Telegram as a link, and Telegram's servers fetch that link without a session — which the new photo protection correctly refuses. Photo messages will fail until the module sends the image data instead. Text and location messages are unaffected.
- **Dokumente:** new documents are stored privately from the moment you update. Documents uploaded *before* the update stay where they are, and stay publicly reachable, until the module registers its path — they are downloadable throughout either way.

## [1.1.9] — 2026-07-29 — Correct product name on container deployments

### Fixed
- **Installations set up in English now actually run as Wintertrace.** Container deployments (Docker/Coolify) never run the web installer, and the brand was seeded as "Schneespur" regardless of the configured language — so an English installation showed the German product name everywhere: interface, PWA and outgoing email. The product name is now derived from the installation language during setup. Existing installations keep the name they have; it can be changed in the settings (see below).
- **Outgoing email no longer goes out under the wrong product name.** With no sender name configured, mail now uses the installed product name instead of falling back to a fixed build constant. A sender name entered in the email settings still takes precedence.

### Added
- **The product name is now a setting.** Betrieb & Standort → Produktname lets an operator switch between Schneespur and Wintertrace at any time. It affects the interface, the PWA (name and home-screen title) and the email sender name.

## [1.1.8] — 2026-07-15 — Admin & help extension seams + fixes

### Fixed
- **Manual job creation no longer blocks silently** when the selected customer has no object yet. Previously the hidden "object" dropdown stayed required and the browser refused to submit without showing any message; now a clear notice explains that the customer needs an object first, and the submit button stays disabled until one exists.
- **Drivers are no longer trapped on the onboarding privacy gate.** The DSGVO consent screen shown before the company data is filled in now offers a logout, so a driver who lands there can sign out instead of being stuck.
- **Offline sync in the driver PWA no longer fails** with an IndexedDB error. Requests queued while offline are now stored and replayed correctly; any entries queued by an older version are migrated automatically on the first load after the update.

### Internal (for module developers)
- New, additive **core extension seams** — dormant until a module uses them, so existing installs behave exactly as before:
  - A generic `@filterSlot` Blade directive plus the `schneespur.admin.job.detail.after` hook (context: the `Job`) lets a module inject an HTML section into the **admin** job-detail page — the counterpart to the PDF seams from 1.1.7. Contributions are per-callback error-isolated, so a broken module section can never break the admin page.
  - `HelpTopicRegistry` lets a module contribute its own topic to the central `/admin/help`, with its own translations (title/description as a locale array or a translation key) and an optional permission gate.
  - New domain events `UserAnonymized` (dispatched after the anonymization transaction commits), `VehicleCreated`, and `VehicleDeleted` (dispatched before the row is removed) give modules reliable lifecycle signals without polling. `UserCreated` is now dispatched after the driver's role is assigned, so listening to that event alone is enough to observe a fully-provisioned driver.
- Module developer documentation updated (events and filter hooks).

## [1.1.7] — 2026-07-13 — Module sections in PDF reports

### Internal (for module developers)
- **Modules can now add their own sections to the generated PDF reports** — the einsatznachweis (job report) and the customer/object collective reports — without touching core. A new `@pdfExtensionSlot` Blade directive over the existing `FilterRegistry` lets a module register a callback that returns HTML; it is inserted at defined points in the PDF. The seam is **dormant until a module uses it**, so existing installs render exactly as before.
  - Per-job hooks `schneespur.pdf.job.after_details`, `schneespur.pdf.job.after_weather`, `schneespur.pdf.job.end` (context: the `Job`) render once in the standalone job report and once per job page in the collective reports.
  - `schneespur.pdf.collective.cover_end` (context: `Customer`, the jobs collection, and the date range) renders once on the collective report cover.
  - Contributions are per-callback error-isolated: a failing module section is logged and skipped, so a broken module can never break the customer-facing PDF.
- Module developer documentation updated (filter hooks and PDF reports).

## [1.1.6] — 2026-06-30 — Security hardening & indexable public pages

### Added
- **Public pages can now be indexed by search engines — selectively.** Since 1.1.2 the whole installation was hidden from search engines to protect customer data. A future **frontpage module** lets a small winter-service business use its installation as a public website; for that, its public pages must be findable. Indexing is now decided per page: everything stays private by default, and only the pages a frontpage module explicitly publishes (the homepage and any extra pages it declares) are exposed to Google — admin, customer portal, driver app and installer always remain private.

### Security
- **Baseline browser-hardening headers** on every response (clickjacking, MIME-sniffing, referrer policy, and HSTS on HTTPS).
- **Host-header injection rejected** — only the configured app host (and its subdomains) is trusted.
- **Proxies are no longer trusted by default**, so a spoofed `X-Forwarded-For` can no longer bypass the login throttle. Operators behind a real CDN/load balancer opt in via `TRUSTED_PROXIES`.
- **Session cookie defaults to `Secure` on HTTPS.**
- **Module supply-chain checks tightened:** downloads are pinned to the configured catalog host, the module slug is validated before any path is built, and ZIP entries with Windows-absolute, backslash, or traversal paths are rejected.
- **Output escaping fixes** — customer names and confirm-dialog messages are escaped in the UI (XSS); confirm dialogs escape their message by default.
- The misleading **SVG option was removed from logo upload** (SVGs can carry scripts).
- The **log mailer is no longer the shipped default** mail transport.
- **Dependencies updated** to clear 19 audit advisories.

### Internal (for module developers)
- New, additive **core extension hooks** prepared for upcoming modules — dormant until a module uses them, so existing installs behave exactly as before:
  - `PublicHomepageRegistry` — serve a public homepage at `/` and control which pages are indexable (frontpage module).
  - `GpsPointReceived` event — observe every driver location ping, even when no job is active (geofencing module).
  - `JobTypeRegistry` — add custom job/activity types without core changes; monthly statistics now aggregate per type via a JSON column (green-care module).
  - `LifecycleFieldRegistry` + the `@lifecycleFields` Blade directive — inject fields with validation and persistence into the four driver lifecycle moments (inventory/green-care modules).
- Module developer documentation updated for all of the above.

## [1.1.5] — 2026-06-23 — Instant module updates & per-user language

### Added
- **Module updates take effect immediately — no server restart.** Previously a module update required restarting the instance for changes to apply, which is effectively impossible on shared hosting (PHP's OPcache kept serving the old bytecode). Wintertrace now clears the compiled caches and resets OPcache automatically after every module **install / update / removal**. Changes apply on the **next page load**.
- **Assignable language per user and per customer.** The language set under *Admin → Users* now applies across the **entire** admin area (previously only driver pages). A customer's language now drives **both the customer portal and emails**, and the picker automatically includes every installed language pack (e.g. Czech, French).
- **Cleaner module page.** Long module descriptions are truncated to 150 characters and expand on demand.

### Security
- The module info-URL scheme is validated server-side (`http`/`https` only), so a tampered catalog cannot inject executable `javascript:` links into the UI.

### Fixed
- The language picker under *Admin → Users* had no effect in the admin area (see above).
- The module catalog was evaluated such that every module was wrongly flagged "Unsigned" and "Community". The misleading labels were removed — modules from the official catalog are no longer shown as third-party. The actual integrity guard (SHA256 check on download) is unchanged and still active. Each module card now links to its **info page**, including the virus-scan result.

### Internal
- Locale application unified into one central `SetUserLocale` web middleware; the duplication in `EnsureDriver` is gone.
- New `ModuleCacheRefresher` lives in the installer, so the admin UI and CLI commands (`modules-sync`, `modules-remove`) benefit equally.
- Release build now excludes the dev example module's assets.

## [1.1.4] — 2026-06-11 — Navigation follows the language

### Fixed
- The **admin menu** and **portal navigation** stayed in German when a non-German UI language was active, while the rest of the page was already translated. Cause: menu labels were frozen at boot time, before the language was applied.
- Menu labels are now resolved **per request in the active language**. From the first additional language pack onward (e.g. French), page **and** navigation appear consistently in the same language.

### Internal
- Both navigation registries (admin & portal) share a `ResolvesNavigationLabels` trait: labels are translation keys resolved at render time, so no consumer can reintroduce the freezing bug.

## [1.1.3] — 2026-06-08 — Multilingual & language packs

### Added
- **Language packs as modules.** New UI languages (e.g. Czech) can be added through a central `LocaleRegistry` without touching core.
- **Language per driver.** Admins assign individual users their own interface language (*Settings → Users*); the driver sees the app in that language at login.
- **Language per customer.** Customers pick their language in the portal profile from all available languages.
- **App-wide language.** The default language (*Settings → Company*) now accepts any installed language; for non-German the brand switches automatically to **Wintertrace**.
- **Portal navigation for modules.** Modules can add their own menu items to the customer portal (desktop **and** mobile) via `PortalNavigationRegistry` — the basis for upcoming portal modules.

### Internal
- Locale validation centralized — no more hard-wired `de/en` lists scattered through the code.
- Adds a `users.locale` migration, applied automatically on update.

## [1.1.2] — 2026-05-30 — Search-engine blocking & log hygiene

### Added
- **Keep installations out of search engines.** Three layers prevent indexing: `robots.txt` (`Disallow: /`), an `X-Robots-Tag: noindex, nofollow` header in `.htaccess`, and `<meta name="robots">` tags across all layouts.
- Icons and Apple/Google app-store links on the OwnTracks help page.

### Fixed
- Removed the per-request module-boot log line (it bloated log files).
- Added 7-day rotation for module logs (`PurgeModuleLogs` command).

## [1.1.1] — 2026-05-30 — Diagnostic reporting for all catch blocks

### Added
- **Full DiagnosticManager integration.** Every try/catch block now reports failures to the diagnostics module — module management (catalog, download, install, migrate, update, settings, remove), the update system, PDF generation, email settings (`.env` write and test-mail errors), customer-portal credential delivery, and dashboard widget/filter execution.

### Fixed
- UTF-8 serialization error: PDF content in queued mails is now base64-encoded.

### Internal
- ~31 catch blocks across 15 files instrumented, all behind a safety wrapper so diagnostic reporting never interrupts the original flow.

## [1.1.0] — 2026-05-30 — The module platform

Five extensibility waves (**M013–M017**) turn the product from a closed system into an open module platform. **Fully backward-compatible** — existing installs keep running unchanged.

> Historical note: this release introduced Ed25519 *module* signing and Official/Verified/Community trust badges. Those were **removed in 1.1.5** because the catalog never sent signatures — SHA256 integrity checking plus virus scanning is the real guard. The entry below is kept as an accurate historical record.

### Added
- **Hook foundation (M013)** — typed `FilterRegistry` with priority ordering and error isolation; 13 domain lifecycle events with 15 dispatch points; pluggable `NotificationChannelRegistry`; automatic module migrations on enable / rollback on remove; namespaced module settings; `requires`/`conflicts` dependency validation with semver constraints.
- **Identity & permissions (M014)** — flexible RBAC replacing the rigid admin/driver enum with many-to-many roles & permissions; `Gate::authorize()` at 87 points across all 32 admin controllers; admin user management at `/admin/users` with last-admin protection; `TwoFactorMethodRegistry` extension point. A bridge keeps the legacy role column in sync → zero breaking changes.
- **UI extensibility (M015)** — a slot system of 15 named slots across admin, driver, and portal layouts via the `@extensionSlot` directive (append/replace semantics); swappable job-dispatch strategies with admin UI.
- **Ops & compliance (M016)** — five new registries (BackupTarget, StorageBackend, ScheduledTask, PdfRenderer, ReportFormat); swappable photo storage with a fallback read chain; a cron overview at `/admin/crontasks` with last-run status, error-isolated; modular PDF renderers and report formats (PDF/CSV defaults).
- **Market opening (M017)** — module trust chain and signature verification; versioned module REST API (`api/mod/{slug}/v{n}/*`) with slug-scoped bearer-token auth; per-module logging (`mod_logs`) with an admin log viewer; module-provided assets and DE/EN translations.

### Internal
- Test suite grown from 88 to **366 tests, 0 failures**. No external package dependencies for RBAC, signing, or the API — deliberately lean for shared hosting.

## [1.0.5] — 2026-05-21 — Bugfix wave (manual job form, branding, GDPR-EN)

### Fixed
- **Manual job entry works again.** A customer name containing `"` broke the HTML attribute in both the admin and driver forms, so Alpine never initialized and the "Create" button did nothing. Switched to Blade's HTML-escaped JSON encoding.
- Hardcoded German error message in the installer preflight now uses the translation layer, so the English installer shows English.

### Added
- **Automatic `:app_name` substitution.** A new `BrandedTranslator` injects the brand into every translation, so help texts, update strings, and mail templates render **Schneespur** / **Wintertrace** correctly everywhere.
- **English GDPR default template** for Wintertrace installs (UK/EU wording, Art. 6(1)(f), full subject-rights enumeration). Both controllers load locale-aware with a German fallback. *Existing English installs with a saved German template must regenerate it once.*
- Icons for all settings cards.

### Internal
- Example module removed from the release build (three-layer guard). Reduced log spam (`Log::info` → `Log::debug`) and new defaults: daily log rotation, `LOG_LEVEL=warning`, 14-day retention. *Existing installs: adjust `.env` for immediate effect.*

## [1.0.4] — 2026-05-19 — Self-updater hotfixes

> Critical. If you installed 1.0.3, apply this so future auto-updates work correctly.

### Fixed
- **Self-updater now unpacks wrapper ZIPs correctly.** Release ZIPs contained a top-level versioned wrapper folder (e.g. `schneespur-1.0.3/`); the old updater staged it verbatim, so new files landed under `<install>/schneespur-X.Y.Z/` instead of overwriting the live code. Fixed with defensive common-prefix stripping in `SchneespurUpdater::extractAndStage`.
- **Rollback guard no longer fires after a successful update.** The counter check ran before same-version detection, so re-checking the same manifest threw a "rollback attempt" error instead of reporting "up to date". The order is reversed.

### Changed
- `build.sh` now produces flat ZIPs (no wrapper folder), so the 1.0.4 update applies correctly even on 1.0.3 installs with the buggy old updater. Release notes include FTP recovery steps for stranded 1.0.3 files.

## [1.0.3] — 2026-05-19 — Hotfixes & module stabilization

### Fixed
- **Logging visible again.** The `reportable()` callback in `bootstrap/app.php` swallowed all exceptions by returning `false`; on a fresh install with no diagnostic reporter, nothing reached `laravel.log`. It now returns `null` so the default logger keeps writing.
- **Creating a site works again.** The `notify_recipients` field had drifted across migration, validation, form, and consumer, causing a 500 on every new site without an explicit recipient. All layers now follow the enum (`customer|object|both`); the form uses a `<select>`.
- **Version display reads from the `VERSION` file.** Footer, settings, and dashboard kept showing `1.0.0` because `config/app.php` had it hardcoded. Config now reads the `VERSION` file dynamically — the single source of truth.
- **Module catalog renders correctly.** Fixed a 500 from `htmlspecialchars` on an i18n dict and empty follow-up loads from a 304 fallback; `normalizeModule()` now maps the server schema to the internal one and caches the catalog body.
- **Modules unpack correctly.** The installer detects and strips a common top-level folder in module ZIPs, so they land under `modules/<slug>/` instead of `modules/<slug>/<slug>/`.

## [1.0.1] — 2026-05-17 — Installer locale detection

### Added
- The installer detects the browser language from the `Accept-Language` header **on the first request** and picks the matching brand automatically: `de-*` → German / **Schneespur**, everything else → English / **Wintertrace**. Previously steps 1–4 were always German regardless of browser.
- A DE/EN switcher in the installer header for manual override.

### Fixed
- The `brand()` helper returns the correct name even without a database connection.

### Internal
- New `SetInstallerLocale` middleware using Symfony's `getPreferredLanguage` for proper q-value handling. Explicit `ext-sodium` Composer requirement (needed by the auto-updater).

## [1.0.0] — 2026-05-17 — First public release

### Added
- Job documentation with GPS track (OwnTracks), photos, and notes
- Automatic weather data at service time (Open-Meteo)
- Customer portal with PDF reports
- Shift logging and time overview
- CSV export and monthly statistics
- GDPR compliance with data export and anonymization
- Progressive Web App (offline-capable)
- Module system for extensions
- Auto-update mechanism

[1.1.6]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.6
[1.1.5]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.5
[1.1.4]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.4
[1.1.3]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.3
[1.1.2]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.2
[1.1.1]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.1
[1.1.0]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.1.0
[1.0.5]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.0.5
[1.0.4]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.0.4
[1.0.3]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.0.3
[1.0.1]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.0.1
[1.0.0]: https://github.com/noschmarrn/Schneespur/releases/tag/v1.0.0
