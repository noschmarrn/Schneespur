<p align="center">
  <img src="schneespur/public/pwa-icon-512x512.png" alt="Schneespur" width="120">
</p>

<h1 align="center">Schneespur</h1>

<p align="center">
  Quelloffene, selbst gehostete Winterdienst-Dokumentation.<br>
  GPS-Tracks &middot; Wetterdaten &middot; Fotos &middot; rechtsfester Einsatznachweis
</p>

<p align="center">
  <a href="https://schneespur.de">schneespur.de</a> &middot;
  <a href="https://wintertrace.com">wintertrace.com</a>
</p>

<p align="center">
  <a href="#english">English</a> &middot;
  <a href="INSTALL.de.md">Installation (DE)</a> &middot;
  <a href="INSTALL.en.md">Installation (EN)</a> &middot;
  <a href="https://schneespur.de/download/">Download</a>
</p>

---

## Was ist Schneespur?

Schneespur dokumentiert Räum- und Streueinsätze für kleine Winterdienst-Betriebe — vollständig, automatisch und rechtssicher. Die Software läuft auf jedem günstigen Shared-Webhosting (Strato, IONOS, All-Inkl, ...) und braucht weder SSH noch Docker.

**Kernversprechen:** Wenn ein Passant auf einer gestreuten Fläche ausrutscht und der Betreiber nachweisen muss, dass er seiner Verkehrssicherungspflicht nachgekommen ist, liefert Schneespur den Beleg — mit GPS-Track, Wetterlage, Fotos und Zeitstempeln.

### Funktionen

- **GPS-Tracking** via [OwnTracks](https://owntracks.org)-App (iOS/Android) — kein eigener Tracking-Client nötig
- **Automatische Wetterdokumentation** — Temperatur, Niederschlag, Wind, Schneelage zum Einsatzzeitpunkt (Open-Meteo, BrightSky, Met.no)
- **Foto-Dokumentation** — Bilder direkt aus der Fahrer-App hochladen
- **PDF-Einsatznachweise** — einzeln oder als Sammelreport pro Kunde und Zeitraum
- **Kundenportal** — Kunden können ihre Einsätze selbst einsehen
- **Fahrer-App (PWA)** — funktioniert offline, synchronisiert automatisch bei Verbindung
- **Kunden- und Objektverwaltung** — mehrere Objekte pro Kunde, Zuordnung zu Einsätzen
- **Fahrzeugverwaltung** — Fuhrpark mit Kennzeichen und Fahrzeugtyp
- **DSGVO-konform** — Fahrer-Anonymisierung, Datenexport, konfigurierbare Aufbewahrungsfristen
- **Automatische Updates** — kryptographisch signiert (Ed25519), ein Klick im Admin-Panel
- **Modulsystem** — erweiterbar über Module aus dem Schneespur-Modulkatalog

### Systemanforderungen

| Komponente | Minimum |
|------------|---------|
| PHP | 8.2 |
| MySQL | 5.7 / MariaDB 10.3 |
| Webserver | Apache mit `mod_rewrite` |
| PHP-Extensions | `pdo_mysql`, `mbstring`, `openssl`, `gd`, `sodium`, `fileinfo` |
| Speicherplatz | ca. 50 MB + Fotos |

### Schnellstart

1. [Download](https://schneespur.de/download/) der aktuellen Version (ZIP)
2. ZIP entpacken und per FTP auf den Webserver laden
3. Document Root auf den `public/`-Ordner setzen
4. Im Browser die Domain aufrufen — der Installations-Assistent führt durch die Einrichtung

Detaillierte Anleitung: **[INSTALL.de.md](INSTALL.de.md)**

### Tech-Stack

| Bereich | Technologie |
|---------|-------------|
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 |
| Karten | Leaflet + OpenStreetMap |
| PDF | DomPDF (rein PHP, kein Chrome/Puppeteer) |
| PWA | Workbox via vite-plugin-pwa |
| Wetter | Open-Meteo / BrightSky / Met.no |

### Lizenz

Schneespur ist lizenziert unter der [GNU Affero General Public License v3.0](LICENSE).

---

<h2 id="english">English</h2>

> The international edition of this software is called **Wintertrace**. The branding is set during installation based on the chosen language.

### What is Schneespur?

Schneespur (German) / Wintertrace (international) is an open-source, self-hosted winter service documentation platform for small snow removal and gritting operators. It runs on any standard shared web hosting (no SSH or Docker required).

**Core promise:** When a pedestrian slips on a cleared surface and the operator needs to prove they fulfilled their duty of care, Schneespur provides the evidence — GPS track, weather conditions, photos, and timestamps.

### Features

- **GPS tracking** via [OwnTracks](https://owntracks.org) app (iOS/Android) — no custom tracking client needed
- **Automatic weather documentation** — temperature, precipitation, wind, snow depth at the time of service (Open-Meteo, BrightSky, Met.no)
- **Photo documentation** — upload images directly from the driver app
- **PDF proof-of-service reports** — individual or batch reports per customer and time period
- **Customer portal** — customers can review their service records
- **Driver app (PWA)** — works offline, syncs automatically when connected
- **Customer & site management** — multiple sites per customer, assigned to jobs
- **Vehicle management** — fleet with license plates and vehicle types
- **GDPR-compliant** — driver anonymization, data export, configurable retention periods
- **Automatic updates** — cryptographically signed (Ed25519), one click in the admin panel
- **Module system** — extensible via modules from the Schneespur module catalog

### System Requirements

| Component | Minimum |
|-----------|---------|
| PHP | 8.2 |
| MySQL | 5.7 / MariaDB 10.3 |
| Web server | Apache with `mod_rewrite` |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `gd`, `sodium`, `fileinfo` |
| Disk space | approx. 50 MB + photos |

### Quick Start

1. [Download](https://wintertrace.com/download/) the latest release (ZIP)
2. Extract and upload via FTP to your web server
3. Set the document root to the `public/` directory
4. Open the domain in your browser — the installation wizard guides you through setup

Detailed guide: **[INSTALL.en.md](INSTALL.en.md)**

### Tech Stack

| Area | Technology |
|------|------------|
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 |
| Maps | Leaflet + OpenStreetMap |
| PDF | DomPDF (pure PHP, no Chrome/Puppeteer) |
| PWA | Workbox via vite-plugin-pwa |
| Weather | Open-Meteo / BrightSky / Met.no |

### License

Schneespur is licensed under the [GNU Affero General Public License v3.0](LICENSE).
