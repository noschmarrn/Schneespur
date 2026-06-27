<p align="center">
  <img src="schneespur/public/pwa-icon-512x512.png" alt="Wintertrace / Schneespur logo" width="120">
</p>

<h1 align="center">Wintertrace &middot; Schneespur</h1>

<p align="center">
  <strong>Open-source, self-hosted winter service software.</strong><br>
  GPS tracking &middot; automatic weather records &middot; photos &middot; court-proof proof of service
</p>

<p align="center">
  <a href="https://wintertrace.com">wintertrace.com</a> &middot;
  <a href="https://schneespur.de">schneespur.de</a> &middot;
  <a href="https://schneespur.cz">schneespur.cz</a>
</p>

<p align="center">
  <a href="README.de.md">🇩🇪 Deutsch</a> &middot;
  <a href="#features">Features</a> &middot;
  <a href="#modules">Modules</a> &middot;
  <a href="INSTALL.en.md">Installation</a> &middot;
  <a href="https://wintertrace.com/download/">Download</a> &middot;
  <a href="CHANGELOG.md">Changelog</a>
</p>

<p align="center">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="License AGPL-3.0" src="https://img.shields.io/badge/license-AGPL--3.0-blue">
  <img alt="Self-hosted" src="https://img.shields.io/badge/self--hosted-shared%20hosting%20ready-2ea44f">
</p>

---

## What is Wintertrace?

**Wintertrace** (internationally) and **Schneespur** (German market) are two brands of the same open-source **winter service software** — a self-hosted platform that **documents snow removal and gritting operations** completely, automatically, and defensibly. It is **winter maintenance software** and **snow management software** built specifically for small operators who plow, salt, and grit pavements, car parks, and access roads.

It runs on cheap standard **shared web hosting** (Strato, IONOS, All-Inkl, OVH, …) — **no SSH, no Docker, no VPS** required. Upload by FTP, open the domain, done.

> **The core promise:** when a pedestrian slips on a surface you cleared and you have to prove you met your **duty of care**, Wintertrace produces the evidence — a **GPS track**, the **weather conditions at the time of service**, photos, and timestamps. That is the difference between a dismissed claim and a paid one.

Which brand a given installation uses is decided once, at install time, from the chosen language: German installs become **Schneespur**, every other language becomes **Wintertrace**. Same code, same database, same modules.

<a id="features"></a>

## Features

- **GPS tracking** via the [OwnTracks](https://owntracks.org) app (iOS / Android) — no custom GPS client to build or maintain
- **Automatic weather documentation** — temperature, precipitation, wind, and snow depth captured for the exact service time (Open-Meteo, BrightSky, Met.no)
- **Photo documentation** — drivers upload before/after photos straight from the field app
- **Court-proof PDF proof-of-service reports** — per job, or batched per customer and period for insurers and clients
- **Customer portal** — clients review their own service records and download reports themselves
- **Driver app (PWA)** — installs like a native app, works **offline**, syncs automatically when back online
- **Customer & site management** — multiple sites per customer, each linked to jobs
- **Fleet / vehicle management** — license plates and vehicle types
- **Roles & permissions (RBAC)** — fine-grained access control, multiple roles per user, last-admin protection
- **GDPR-compliant** — driver anonymization, data export, configurable retention periods
- **Self-updating** — cryptographically signed (Ed25519) update packages, one click in the admin panel
- **Extensible module platform** — add features without touching core (see [Modules](#modules))

<a id="modules"></a>

## Modules

Wintertrace ships as a lean core and grows through installable modules from the official catalog. Browse and install them from inside the admin panel, or see the full list at **[wintertrace.com/modules](https://wintertrace.com/modules)**.

| Module | What it does |
|--------|--------------|
| **Documents** | Store and manage documents, assigned to customers and sites |
| **Telegram Notifications** | Push winter-service alerts to admins, drivers, and customers via Telegram |
| **Diagnostics** | Forwards technical errors to the maintainer so they can be fixed — no personal data |
| **Language Packs** | Add interface languages (Czech, French, … ) without touching core |

Every module is **integrity-checked (SHA256)** on download and **virus-scanned**; each catalog entry links its scan report, so you can see exactly what you are installing. Modules can extend navigation and dashboards, add roles, register weather providers, swap storage/backup/PDF backends, and hook into 13+ lifecycle events. Developers: see the module system docs (`moduldoku.md`) to build your own.

## Why self-hosted & open source?

- **Your data stays yours** — operational and customer data live on your hosting, not a vendor's cloud
- **No per-seat SaaS fees** — install once, run on hosting you already pay for
- **Auditable** — the full source is here under [AGPL-3.0](LICENSE); nothing about how your evidence is generated is hidden
- **No lock-in** — export your data, read your own database, fork the code

## System requirements

| Component | Minimum |
|-----------|---------|
| PHP | 8.2 |
| MySQL / MariaDB | 5.7 / 10.3 |
| Web server | Apache with `mod_rewrite` |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `gd`, `sodium`, `fileinfo` |
| Disk space | ~50 MB + photos |

Works on entry-level shared hosting. No root, no containers, no command line required for installation.

## Quick start

1. [Download](https://wintertrace.com/download/) the latest release (ZIP)
2. Extract and upload via FTP to your web server
3. Point the document root at the `public/` directory
4. Open the domain in your browser — the install wizard handles database setup, branding, and the admin account

Detailed guide: **[INSTALL.en.md](INSTALL.en.md)**

## Tech stack

| Area | Technology |
|------|------------|
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 |
| Maps | Leaflet + OpenStreetMap |
| PDF | DomPDF (pure PHP — no Chrome / Puppeteer) |
| PWA | Workbox via vite-plugin-pwa |
| Weather | Open-Meteo / BrightSky / Met.no |

Deliberately dependency-light so it runs on shared hosting: RBAC, update signing, and the module API are all built in, with no external services to provision.

## Documentation & links

- **Websites:** [wintertrace.com](https://wintertrace.com) (international) · [schneespur.de](https://schneespur.de) (Germany) · [schneespur.cz](https://schneespur.cz) (Czech Republic)
- **Modules:** [wintertrace.com/modules](https://wintertrace.com/modules)
- **Install guide:** [English](INSTALL.en.md) · [Deutsch](INSTALL.de.md)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md) · [GitHub Releases](https://github.com/noschmarrn/Schneespur/releases)
- **German README:** [README.de.md](README.de.md)

## License

Licensed under the [GNU Affero General Public License v3.0](LICENSE).

---

<sub>Keywords: winter service software · winter maintenance software · snow removal software · snow management software · gritting & salting documentation · GPS winter service tracking · proof of service · duty-of-care evidence · self-hosted · open source. German edition: see <a href="README.de.md">README.de.md</a>.</sub>
