# Changelog

All notable changes to **Wintertrace / Schneespur** are documented here. The same releases are published as [GitHub Releases](https://github.com/noschmarrn/Schneespur/releases) and offered in-app through the self-updater.

This project follows [Semantic Versioning](https://semver.org/). Unless noted, releases are backward-compatible and need no manual steps — just install the update.

---

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
