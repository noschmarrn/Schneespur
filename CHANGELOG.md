# Changelog

All notable changes to **Wintertrace / Schneespur** are documented here. The same releases are published as [GitHub Releases](https://github.com/noschmarrn/Schneespur/releases) and offered in-app through the self-updater.

This project follows [Semantic Versioning](https://semver.org/). Unless noted, releases are backward-compatible and need no manual steps — just install the update.

---

## [Unreleased]

### Added
- **Addresses now carry a state, province or canton**, and are printed in the format of the operator's region — `Innstraße 12, 94032 Passau` in Germany, `400 S 4th St, Minneapolis, MN 55401` in the United States. Company & Location and each customer object gained a state field to go with it. Thirteen places that used to compose the address by hand — the customer and object lists, job details, both PDF reports, the customer portal, the caption burned into every job photo, and the placeholder text in the privacy notice — now share one source, so all of them gained the state at once instead of one at a time. The photo caption is the one that could not have been corrected afterwards: it is drawn into the image itself when the photo is uploaded.
- **The CSV export gained a column, "Site State / Province" (Objekt-Bundesland / Kanton)**, right after the object's city. This widens every row by one field — worth knowing if you parse the file by column position rather than by its header.
- **The region template now also sets the CSV delimiter and the first day of the week**, alongside the address format. All three stay individually adjustable under *Units & Formats*, the same way the other regional settings already were.
- **Site notes reach the driver.** A collapsible box in the driver app shows the site's access notes, job notes, plow threshold and salt setting — once a site is selected, before the job starts, and again while the job runs. It reads the data already loaded in the app, so it needs no connection of its own. The salt line only appears once at least one of your sites has "use salt" ticked: until then the setting carries no information, because it was off by default and nothing ever read it, and showing it would print a red "Do not use salt" at every existing site. Once it is in use, "Do not use salt" is highlighted in red for every site where salting is switched off, and a site where salting is allowed says so in a quieter line. **After updating, review the salt setting of your sites** — an unticked box reads as "do not use salt" as soon as the setting is in use. A site with nothing to show gets no box.
- **Internal note on a job.** A second note field that only your company sees: the driver enters it when ending a job or entering one after the fact, and an administrator can edit it on the job (the change is recorded in the job's history). It never appears in the customer portal, on any PDF, or in any email. The existing note field stays visible to the customer and now says so beneath the input in the driver app.
- **A customer has its own address** (street, postal code, city, state). The collective report prints it instead of the address of the customer's first site, which was systematically wrong for a property manager whose sites are other people's buildings.
- **Prices per job type and per hour.** A site keeps its base price; below it, a table lets each job type — including job types added by modules — carry its own price in its own unit. Leave a row empty and the base price applies. "Per hour" is a new unit, billed to the minute or per started 15, 30 or 60 minutes (*Admin → Settings → Company & Location → Billing*).
- **Amounts are evaluated.** The customer report shows the amount per job, flat rates as separate lines, and a total for the period. The CSV export has three new columns at the end — amount, price unit and rate — written in your number format without a currency symbol, so a spreadsheet can calculate with them.
- **The collective report can carry the amounts too** — a block titled "Amounts in this period" with the total, marked as a record of services rather than an invoice. It is switched off by default and set under *Admin → Settings → Company & Location → Billing*, so installing this update changes nothing in what your customers receive. Once on, it applies to every copy of the collective report: the PDF you download, the report for a single site, the PDF a customer downloads in the portal, and the PDF attached to the report email. The single-job proof of service, the portal pages and the email text never show an amount.

### Security
- **A user without a role no longer receives customer and site data on the dashboard.** A signed-in user who was neither administrator nor driver was shown the driver dashboard with every customer and site loaded into the page, and since site notes reach the driver that included the gate codes. Such a user, and one whose custom role lacks the customer permission, now gets the dashboard without any customer, site or vehicle data.

### Known limitations
- **A per-hour price counts the time a phone spent offline.** If a driver ends a job while the phone has no signal, the end is time-stamped when the phone reconnects, not when "End job" was pressed, so the amount of a job priced per hour includes the time without signal. Until this is fixed, delete such a job and enter it again manually with the correct times.

### Changed
- **PDF reports now print the service address across two lines** (street, then postal code and city) instead of packed onto one line with a comma. This affects every job report and customer report from now on, in all three address formats, and is a side effect of the address now having a state to make room for.
- **The system requirement is stated as PHP 8.4.** It always was that in practice: every release since 1.1.7 shipped a dependency set that refuses to start below PHP 8.4.1, while the README, both install guides and the in-app help promised 8.2. On 8.2 or 8.3 that combination produces a blank error page on every request, with nothing explaining why — the check that produces it runs before the application starts, so the setup wizard never gets the chance to name the problem. The requirement is now stated correctly everywhere, including the wizard's own check, and the dependency resolver is pinned to 8.4.1 so that building on a machine with a newer PHP cannot raise the bar unnoticed a second time. Nothing changes for an installation that is running: if yours works, it is already on 8.4 or newer.

  Lowering the requirement instead was weighed and left for later. PHP 8.3 is within reach but costs around forty dependency updates including a Laravel minor version, and 8.2 is out of reach altogether because the image library requires 8.3 or newer.
- **The price of a job is fixed when the job is completed.** Changing a price later does not change earlier jobs. Jobs completed before this release carry no amount, and so do jobs completed while no price was on file; the customer report says how many jobs have none instead of counting them as zero. The hourly increment is fixed with the job as well, so changing the setting affects future jobs only.
- **Monthly and seasonal flat rates** are counted per started calendar month that lies in the season, and per season, using the rate on file at the time of the evaluation rather than at the time of the jobs. A period outside every season carries no flat rate, and a period whose start lies after its end is evaluated as empty.
- **Collective report without a customer address:** if a customer has no address of its own and more than one site, the report now prints no customer address rather than the address of the first site, and the customer list says "No customer address on file". Customers with exactly one site are unaffected: the address of that site is still printed.

### Fixed

Twelve defects found by two independent field tests of 1.2.0 — one German operation, one American — that put the release into daily use rather than into a test suite. Both reports arrived at the same blockers: time zones and the season period, and in the American test the address lookup as well. Those three lead the list. Five more entries below concern weather: the German report also named weather going missing on jobs entered after the fact, which is fixed here; tracing that down turned up four further defects — a "Retry" button that could not be reached and would not have worked if it had been, a snow depth stored a hundred times too small, a snow depth reported as "0.0 cm" by two providers that never measure it at all, and a proof-of-service email that had the number to show and never showed it.

- **The time zone setting had no effect, and manually entered jobs were off by an hour or two.** The setup wizard has always asked for a time zone and always stored the answer; nothing ever read it back. Times typed into a manual job were then taken as though they were UTC, so a job entered as 08:30 in Germany was stored as 08:30 UTC and shown back as 09:30 — an hour out in winter, two in summer, and six the other way in Minneapolis. All of it now follows your operation's zone: what you type, what the software refuses as lying in the future, what every screen, report, email and PDF shows, and the running shift clock in the driver app, which until now used the phone's own zone rather than yours. **Jobs entered before this update are corrected automatically**, each by the offset that applied on its own date, so summer and winter entries move by the right amount and jobs recorded from GPS are left alone. One case cannot be corrected and is worth knowing about: if you already knew the display was an hour out and compensated while typing, those entries move a second time and need fixing by hand.
- **The time zone can be changed after installation** — *Admin → Settings → Company* — and the list now covers the world instead of six European entries. That short list was itself the defect: the wizard offers to detect your zone but only applied one that was already in the list, so an installation in Chicago or Denver silently stayed on Berlin without anyone having chosen it.
- **The season period lost October, November and December.** Choosing a season that starts in November stored January instead, and the list of end dates offered "November 31", a day that does not exist. Both symptoms came from the same defect, and both are gone: the season now starts and ends in the month you pick, and the end dates offered are the real ones for each month. If your season period looks wrong, open *Admin → Settings → Company* and set it once — the value has been silently reset to January every time any other setting on that page was saved, so it is worth checking even if you set it long ago. Jobs driven in November and December were never lost; they simply fell outside every season-based evaluation.
- **Address lookup only worked for German addresses.** The "find coordinates" button asked the map service to search Germany and nowhere else, which is why it worked in Passau and failed in Minneapolis, Vienna and Zurich alike — even though the software offers regional templates for six countries. It now searches worldwide, with no country restriction of any kind, so a German operation can equally place an object across the border.
- **The customer's language setting never reached their email.** Every notice went out in the installation's language regardless of what was set on the customer. The language is now applied before the message is composed, which is the only point at which it has any effect. Related: on an English installation, new customers were created as German-speaking; they now start in the installation's language.
- **PDF attachments were always named in German.** Every proof of service arrived as `einsatznachweis-…`, including for recipients who cannot read that word. Filenames now follow the language of the installation.
- **The weather column showed a number instead of a description.** Admin and customer portal printed a bare `1` where the PDF has always written "Mostly clear". Both now use the same descriptions as the PDF — which also means light, moderate and heavy snowfall are told apart on screen, and a job with no weather data on record says so instead of claiming "Cloudy".
- **English decimal points in a German interface.** The customer portal wrote "1.8" hours and the customer evaluation "0.1 jobs/week", while the rest of the software wrote "12,5". Both now follow the separator you set under *Units & Formats*.
- **The credentials email showed raw asterisks** around "Email" and "Password" in its plain-text version.
- **A mistyped address produced Laravel's untranslated error page** — "404 | NOT FOUND", unstyled, with no way back, in the German customer portal as much as anywhere else. There is now a proper page in your language with a link back to where you came from.
- **Correcting one field in a manual job form emptied the customer and object selection.** After a validation error the two dropdowns reset, so fixing the reported problem produced the next one. Both the administrator and the driver version of the form now keep what was selected.
- **Warnings and error messages disappeared after five seconds.** They now stay until closed; success messages still fade, since they only confirm what is already visible. This is what made a failed address lookup look like nothing had happened at all, and in the driver app it was the only explanation a driver ever got for why a shift or a job would not start — five seconds of it, on a phone, in the dark, wearing gloves.
- **A job entered after the fact carried the weather of the day it was typed in, not the day it happened.** A January shift entered in September was recorded with September's weather — not a gap, a wrong entry, and the proof of service handed to the customer said so. The weather is now fetched for the actual hour of the job from the provider's archive: Open-Meteo (both the free and the API tier) and Bright Sky (DWD) can look back, and each of them is now checked to have returned the hour that was asked for rather than whichever hour it happened to send. MET Norway has no archive, so on that provider the field is left empty rather than filled with today's weather — and that outcome is now treated as the settled answer it is, instead of as a failed retrieval that is retried and logged as an incident on every single job. **Jobs already entered are not touched** — to correct one, open it and press "Retry" on the weather.
- **The "Retry" button on the weather was unreachable, and would not have worked.** It was only ever drawn for a state that cannot occur, so on a job that has weather — the one case where you would want it — it was simply not there. Underneath, it queued the request for a background worker and reported success; on an installation without a scheduled task, which ordinary web hosting often is, nothing ever picked that request up. Both are fixed: the button now appears on every weather row, including for a moment that has no reading at all, and where there is no background worker it fetches the weather then and there and tells you what actually came back. It replaces the existing value rather than leaving it standing.

  **Correcting the weather does not send the customer anything.** That is deliberate — a diagnostic button must not put mail in a customer's inbox on every click — but it has a consequence worth stating plainly: the customer keeps the proof of service you have just established to be wrong, and there is no button today that sends them a corrected one. If a customer has already received a proof whose weather you correct, you need to tell them yourself.
- **The snow depth was off by a factor of a hundred.** 15 cm was recorded and shown as "0.2 cm" — the provider reports snow depth in metres, and the value was stored as if it were already centimetres. Every screen, the PDF proof of service and the customer portal are affected; the fix converts on the way in, and **existing records are corrected automatically**. One display lags briefly: the weather forecast tile on the dashboard is cached, so it can still show the old scale until that cache expires — at most as long as the cache duration set under *Admin → Settings → Weather*.
- **A snow depth of "0.0 cm" was shown for two providers that never measure it.** Bright Sky (DWD) and MET Norway report no snow depth at all, and both filled the field with zero — which does not read as "not measured", it reads as "no snow". A German operation on Bright Sky therefore handed its customers a proof of service stating "0.0 cm of snow" for every job, in the middle of a snowfall included. The field is now left blank wherever no measurement exists, on screen, in the PDF and in the email, and **existing records are corrected automatically**. A measured zero is still shown: on a gritting run the absence of snow is itself part of the record.
- **The proof-of-service email now names the snow depth**, which the PDF and the customer portal have stated all along.

Five more, found while giving customer objects a state field and their address one shared source:

- **The CSV export used a semicolon regardless of region.** In English-speaking Excel that put every row into a single column, which made the export unusable rather than merely untidy. The delimiter now follows the region template — semicolon for the German-speaking templates, comma elsewhere — and stays adjustable on its own under *Units & Formats*.
- **The week began on Monday everywhere**, including for operators whose week starts on Sunday. The monthly calendar now begins on the region's configured first day of the week, header row included, and so do the "This week" button in the driver and customer reports and the "Last week" shortcut above every date range — which until now disagreed with the calendar sitting next to them.
- **A plow threshold could only be whole centimetres, so 2″ (5.08 cm) could not be entered at all.** It is now stored the way the operator entered it, in their own unit, and read back without a rounding loss — the field itself is now labelled with the unit, "Plow threshold (in)" or "Plow threshold (cm)".
- **On Company & Location, a failed address lookup asked the operator to enter coordinates by hand.** There is no field for that, and the block that would show the coordinates is not rendered at all when the lookup failed. The message now asks to check the street, postal code and city instead.
- **The customer portal's object list showed the street and city, but never the postal code.**

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
