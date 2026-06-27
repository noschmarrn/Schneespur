<p align="center">
  <img src="schneespur/public/pwa-icon-512x512.png" alt="Schneespur / Wintertrace Logo" width="120">
</p>

<h1 align="center">Schneespur &middot; Wintertrace</h1>

<p align="center">
  <strong>Quelloffene, selbst gehostete Winterdienst-Software.</strong><br>
  GPS-Tracking &middot; automatische Wetterdaten &middot; Fotos &middot; rechtssicherer Einsatznachweis
</p>

<p align="center">
  <a href="https://schneespur.de">schneespur.de</a> &middot;
  <a href="https://wintertrace.com">wintertrace.com</a> &middot;
  <a href="https://schneespur.cz">schneespur.cz</a>
</p>

<p align="center">
  <a href="README.md">🇬🇧 English</a> &middot;
  <a href="#funktionen">Funktionen</a> &middot;
  <a href="#module">Module</a> &middot;
  <a href="INSTALL.de.md">Installation</a> &middot;
  <a href="https://schneespur.de/download/">Download</a> &middot;
  <a href="CHANGELOG.md">Changelog</a>
</p>

<p align="center">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="Lizenz AGPL-3.0" src="https://img.shields.io/badge/Lizenz-AGPL--3.0-blue">
  <img alt="Self-hosted" src="https://img.shields.io/badge/self--hosted-Shared%20Hosting-2ea44f">
</p>

---

## Was ist Schneespur?

**Schneespur** ist quelloffene **Winterdienst-Software**, mit der kleine Betriebe ihren **Winterdienst dokumentieren** — vollständig, automatisch und rechtssicher. Sie ist **Software für Winterdienst, Räum- und Streueinsätze**: für Gehwege, Parkplätze und Zufahrten, die geräumt, gestreut und gesalzen werden.

Schneespur läuft auf günstigem, normalem **Shared-Webhosting** (Strato, IONOS, All-Inkl, OVH, …) — **ohne SSH, ohne Docker, ohne eigenen Server**. Per FTP hochladen, Domain aufrufen, fertig.

> **Das Kernversprechen:** Wenn ein Passant auf einer von Ihnen gestreuten Fläche ausrutscht und Sie nachweisen müssen, dass Sie Ihrer **Verkehrssicherungspflicht** (Räum- und Streupflicht) nachgekommen sind, liefert Schneespur den Beleg — mit **GPS-Track**, **Wetterlage zum Einsatzzeitpunkt**, Fotos und Zeitstempeln. Das ist der Unterschied zwischen einer abgewiesenen und einer bezahlten Forderung.

International heißt dieselbe Software **Wintertrace**. Welche Marke eine Installation trägt, entscheidet sich einmalig bei der Installation anhand der gewählten Sprache: Deutsche Installs werden zu **Schneespur**, alle anderen Sprachen zu **Wintertrace**. Gleicher Code, gleiche Datenbank, gleiche Module.

<a id="funktionen"></a>

## Funktionen

- **GPS-Tracking** über die [OwnTracks](https://owntracks.org)-App (iOS / Android) — kein eigener Tracking-Client nötig
- **Automatische Wetterdokumentation** — Temperatur, Niederschlag, Wind und Schneelage exakt zum Einsatzzeitpunkt (Open-Meteo, BrightSky, Met.no)
- **Foto-Dokumentation** — Fahrer laden Vorher/Nachher-Bilder direkt aus der Feld-App hoch
- **Rechtssichere PDF-Einsatznachweise** — einzeln oder als Sammelreport pro Kunde und Zeitraum, für Versicherung und Auftraggeber
- **Kundenportal** — Kunden sehen ihre Einsätze selbst ein und laden Berichte herunter
- **Fahrer-App (PWA)** — installiert sich wie eine native App, funktioniert **offline**, synchronisiert automatisch bei Verbindung
- **Kunden- und Objektverwaltung** — mehrere Objekte pro Kunde, jedes mit Einsätzen verknüpft
- **Fuhrpark-/Fahrzeugverwaltung** — Kennzeichen und Fahrzeugtypen
- **Rollen & Berechtigungen (RBAC)** — feingranulare Zugriffssteuerung, mehrere Rollen pro Benutzer, Schutz des letzten Admins
- **DSGVO-konform** — Fahrer-Anonymisierung, Datenexport, konfigurierbare Aufbewahrungsfristen
- **Selbst-Update** — kryptographisch signierte (Ed25519) Update-Pakete, ein Klick im Admin-Panel
- **Erweiterbare Modul-Plattform** — neue Funktionen ohne Eingriff in den Kern (siehe [Module](#module))

<a id="module"></a>

## Module

Schneespur wird als schlanker Kern ausgeliefert und wächst über installierbare Module aus dem offiziellen Katalog. Sie lassen sich direkt im Admin-Panel installieren — die vollständige Übersicht finden Sie unter **[schneespur.de/module](https://schneespur.de/module)**.

| Modul | Was es leistet |
|-------|----------------|
| **Dokumente** | Dokumente verwalten und Kunden sowie Objekten zuweisen |
| **Telegram-Benachrichtigungen** | Winterdienst-Benachrichtigungen per Telegram an Admins, Fahrer und Kunden |
| **Diagnose** | Sendet technische Fehler an den Betreiber zur Behebung — keine persönlichen Daten |
| **Sprachpakete** | Weitere Oberflächensprachen nachrüsten (Tschechisch, Französisch, …) ohne Kern-Eingriff |

Jedes Modul wird beim Download per **SHA256 integritätsgeprüft** und **auf Viren gescannt**; jeder Katalog-Eintrag verlinkt sein Scan-Ergebnis — Sie sehen genau, was Sie installieren. Module können Navigation und Dashboards erweitern, Rollen mitbringen, Wetter-Provider registrieren, Storage-/Backup-/PDF-Backends austauschen und an über 13 Lifecycle-Events andocken.

## Warum selbst gehostet & Open Source?

- **Ihre Daten bleiben Ihre** — Betriebs- und Kundendaten liegen auf Ihrem Hosting, nicht in der Cloud eines Anbieters
- **Keine SaaS-Gebühren pro Platz** — einmal installieren, auf vorhandenem Hosting betreiben
- **Nachprüfbar** — der vollständige Quellcode liegt hier unter [AGPL-3.0](LICENSE); nichts an der Erzeugung Ihrer Nachweise ist versteckt
- **Kein Lock-in** — Daten exportieren, eigene Datenbank lesen, Code forken

## Systemanforderungen

| Komponente | Minimum |
|------------|---------|
| PHP | 8.2 |
| MySQL / MariaDB | 5.7 / 10.3 |
| Webserver | Apache mit `mod_rewrite` |
| PHP-Extensions | `pdo_mysql`, `mbstring`, `openssl`, `gd`, `sodium`, `fileinfo` |
| Speicherplatz | ca. 50 MB + Fotos |

Läuft auf Einsteiger-Webhosting. Kein Root, keine Container, keine Kommandozeile für die Installation nötig.

## Schnellstart

1. [Download](https://schneespur.de/download/) der aktuellen Version (ZIP)
2. ZIP entpacken und per FTP auf den Webserver laden
3. Document Root auf den `public/`-Ordner setzen
4. Domain im Browser aufrufen — der Installations-Assistent erledigt Datenbank, Branding und Admin-Konto

Ausführliche Anleitung: **[INSTALL.de.md](INSTALL.de.md)**

## Tech-Stack

| Bereich | Technologie |
|---------|-------------|
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 |
| Karten | Leaflet + OpenStreetMap |
| PDF | DomPDF (rein PHP — kein Chrome / Puppeteer) |
| PWA | Workbox via vite-plugin-pwa |
| Wetter | Open-Meteo / BrightSky / Met.no |

Bewusst abhängigkeitsarm, damit es auf Shared-Hosting läuft: RBAC, Update-Signierung und die Modul-API sind eingebaut — keine externen Dienste nötig.

## Dokumentation & Links

- **Webseiten:** [schneespur.de](https://schneespur.de) (Deutschland) · [wintertrace.com](https://wintertrace.com) (international) · [schneespur.cz](https://schneespur.cz) (Tschechien)
- **Module:** [schneespur.de/module](https://schneespur.de/module)
- **Installationsanleitung:** [Deutsch](INSTALL.de.md) · [English](INSTALL.en.md)
- **Changelog:** [CHANGELOG.md](CHANGELOG.md) · [GitHub Releases](https://github.com/noschmarrn/Schneespur/releases)
- **English README:** [README.md](README.md)

## Lizenz

Lizenziert unter der [GNU Affero General Public License v3.0](LICENSE).

---

<sub>Schlagwörter: Winterdienst-Software · Software für Winterdienst · Winterdienst dokumentieren · Winterdienst-Dokumentation · GPS Winterdienst · Streugut-Nachweis · Einsatznachweis · Verkehrssicherungspflicht · Räum- und Streupflicht · selbst gehostet · Open Source. Internationale Ausgabe: siehe <a href="README.md">README.md</a>.</sub>
