# Schneespur — Installationsanleitung

Diese Anleitung beschreibt die Installation von Schneespur auf einem klassischen Shared-Webhosting (Strato, IONOS, All-Inkl o. ä.) mit PHP und MySQL. SSH oder Docker sind **nicht** erforderlich.

---

## Inhaltsverzeichnis

1. [Systemvoraussetzungen](#1-systemvoraussetzungen)
2. [Dateien hochladen](#2-dateien-hochladen)
3. [Document-Root konfigurieren](#3-document-root-konfigurieren)
4. [Datenbank anlegen](#4-datenbank-anlegen)
5. [Installations-Assistent](#5-installations-assistent)
6. [Cron-Job einrichten](#6-cron-job-einrichten)
7. [OwnTracks einrichten](#7-owntracks-einrichten)
8. [Update-Anleitung](#8-update-anleitung)
9. [Backup](#9-backup)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Systemvoraussetzungen

| Anforderung | Minimum | Empfohlen |
|-------------|---------|-----------|
| PHP | 8.2 | 8.3 oder 8.4 |
| MySQL | 5.7 | 8.0+ |
| MariaDB (alternativ) | 10.3 | 10.6+ |

### Benoetigte PHP-Erweiterungen

**Pflicht** (Installation schlaegt ohne diese fehl):

- `pdo_mysql`
- `gd`

**Empfohlen** (Warnungen im Assistenten, wenn fehlend):

- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`

> Die meisten Shared-Hosting-Anbieter haben alle genannten Erweiterungen bereits aktiviert.

### Weitere Voraussetzungen

- FTP- oder Dateimanager-Zugang zum Webspace
- Eine MySQL/MariaDB-Datenbank (wird vom Hoster bereitgestellt)
- Das Document-Root muss auf einen Unterordner (`/public`) zeigbar sein

---

## 2. Dateien hochladen

1. Laden Sie das aktuelle Schneespur-Release herunter (ZIP-Archiv).
2. Entpacken Sie das Archiv auf Ihrem Computer.
3. Laden Sie den gesamten Inhalt per FTP oder Dateimanager in Ihr Webverzeichnis hoch, z. B. `/schneespur/` oder direkt ins Hauptverzeichnis.

**Ordnerstruktur nach dem Upload:**

```
/schneespur/
  app/
  bootstrap/
  config/
  database/
  lang/
  public/          <-- hierhin muss das Document-Root zeigen
  resources/
  routes/
  storage/
  vendor/
  .env.example
  artisan
  composer.json
  ...
```

---

## 3. Document-Root konfigurieren

Das Document-Root (manchmal auch „Webroot" oder „Stammverzeichnis" genannt) Ihrer Domain muss auf den Unterordner `public/` zeigen.

**Beispiel:** Wenn Sie die Dateien nach `/schneespur/` hochgeladen haben, setzen Sie das Document-Root auf `/schneespur/public/`.

So geht das bei gaengigen Hostern:

- **Strato:** Paket-Verwaltung → Domain-Verwaltung → Umleitung/Ziel → Pfad aendern
- **IONOS:** Hosting → Domains → Document-Root bearbeiten
- **All-Inkl:** Domain-Einstellungen → Ordnerzuordnung

> **Wichtig:** Wenn Ihr Hoster keinen Unterordner als Document-Root erlaubt, verschieben Sie den Inhalt von `public/` ins Hauptverzeichnis und passen Sie die Pfade in `index.php` entsprechend an. Der Installations-Assistent hilft dabei nicht — kontaktieren Sie im Zweifel Ihren Hoster.

---

## 4. Datenbank anlegen

Erstellen Sie ueber das Verwaltungspanel Ihres Hosters eine neue MySQL-Datenbank. Notieren Sie sich:

- **Host** (z. B. `localhost` oder `rdbms.strato.de`)
- **Port** (Standard: `3306`)
- **Datenbankname**
- **Benutzername**
- **Passwort**

Diese Daten benoetigen Sie im naechsten Schritt.

---

## 5. Installations-Assistent

Oeffnen Sie Ihre Domain im Browser. Schneespur erkennt automatisch, dass noch keine Installation vorliegt, und startet den Assistenten.

### Schritt 1: Willkommen

Der Assistent prueft die Grundvoraussetzungen und erzeugt die Konfigurationsdatei (`.env`) sowie den Anwendungsschluessel (`APP_KEY`).

### Schritt 2: Datenbank

Geben Sie die Zugangsdaten aus Schritt 4 ein. Der Assistent testet die Verbindung, bevor er fortfaehrt.

> Falls die `.env`-Datei nicht beschreibbar ist (selten bei Shared-Hosting), zeigt der Assistent eine Anleitung zum manuellen Bearbeiten per FTP an.

### Schritt 3: Systemcheck

Der Assistent prueft PHP-Version, Erweiterungen und Schreibrechte auf wichtige Verzeichnisse (`storage/`, `bootstrap/cache/`). Fehlende Erweiterungen werden als Pflicht oder Empfehlung markiert.

### Schritt 4: Datenbank-Migration

Die Datenbanktabellen werden automatisch angelegt. Dieser Schritt kann bei Fehlern beliebig oft wiederholt werden, ohne Datenverlust.

### Schritt 5: Anwendungskonfiguration

Legen Sie fest:

- **App-URL** (Ihre Domain, z. B. `https://schneespur.meinefirma.de`)
- **Zeitzone** (z. B. `Europe/Berlin`)
- **Sprache** (`de` oder `en`)

### Schritt 6: Speicher & Caches

Der Assistent erstellt die Verknuepfung zum oeffentlichen Speicher (`storage:link`) und baut Caches auf. Falls die Verknuepfung auf Ihrem Hoster nicht funktioniert, wird eine Anleitung zum manuellen Anlegen per FTP angezeigt.

### Schritt 7: Admin-Konto

Erstellen Sie Ihr Administrator-Konto (Name, E-Mail, Passwort mit mindestens 8 Zeichen).

### Schritt 8: E-Mail-Konfiguration (optional)

Richten Sie SMTP-Versand ein, damit Schneespur Benachrichtigungen senden kann. Dieser Schritt kann uebersprungen und spaeter in den Einstellungen nachgeholt werden.

### Fertig

Nach Abschluss sehen Sie eine Zusammenfassung. Sie koennen sich jetzt mit Ihren Admin-Zugangsdaten anmelden.

---

## 6. Cron-Job einrichten

Schneespur benoetigt einen Cron-Job, der einmal pro Minute den Laravel-Scheduler ausfuehrt. Dieser verarbeitet die Auftragswarteschlange (z. B. Wetterdaten abrufen, Benachrichtigungen senden).

### Cron-Befehl

```
* * * * * /usr/local/bin/php /pfad/zu/schneespur/artisan schedule:run >> /dev/null 2>&1
```

> **Wichtig:** Ersetzen Sie `/pfad/zu/schneespur/` durch den tatsaechlichen Pfad auf Ihrem Webspace und `/usr/local/bin/php` durch den PHP-Pfad Ihres Hosters (haeufig auch `/usr/bin/php` oder `/usr/bin/php8.3`).

### So richten Sie den Cron-Job ein

- **Strato:** Paket-Verwaltung → Cron-Jobs → Neuer Cronjob
- **IONOS:** Hosting → Cron-Jobs → Cronjob anlegen
- **All-Inkl:** Tools → Cronjobs → Neuer Cronjob

Stellen Sie die Ausfuehrung auf **jede Minute** oder das kuerzeste verfuegbare Intervall.

### Warum ist der Cron-Job noetig?

Ohne Cron-Job werden keine Hintergrundaufgaben verarbeitet:

- Wetterdaten werden nicht automatisch zu Einsaetzen hinzugefuegt
- E-Mail-Benachrichtigungen werden nicht versendet
- Geplante Aufgaben laufen nicht

---

## 7. OwnTracks einrichten

OwnTracks ist die GPS-Tracking-App, mit der Ihre Fahrer die Einsaetze aufzeichnen. Jeder Fahrer benoetigt die App auf seinem Smartphone.

### Kurzanleitung

1. **App installieren:** OwnTracks aus dem App Store (iOS) oder Google Play Store (Android) herunterladen.
2. **Zugangsdaten erzeugen:** Melden Sie sich als Admin in Schneespur an, oeffnen Sie die Fahrer-Uebersicht und klicken Sie beim jeweiligen Fahrer auf „Zugangsdaten". Schneespur erzeugt automatisch Benutzername und Passwort.
3. **QR-Code scannen:** Auf der Zugangsdaten-Seite wird ein QR-Code angezeigt. Der Fahrer scannt diesen mit der OwnTracks-App, und die Verbindung wird automatisch konfiguriert.
4. **Manuell konfigurieren** (falls QR-Code nicht funktioniert):
   - Modus: **HTTP**
   - URL: `https://ihre-domain.de/api/owntracks/report`
   - Benutzername und Passwort: wie in Schneespur angezeigt
5. **Testen:** Oeffnen Sie in Schneespur unter „OwnTracks" die Uebersicht. Sobald der Fahrer die App startet, sollte dort ein gruener Status erscheinen.

---

## 8. Update-Anleitung

### Vor dem Update

1. Erstellen Sie ein Backup (siehe [Backup](#9-backup)).
2. Aktivieren Sie den Wartungsmodus: Oeffnen Sie `https://ihre-domain.de/down` im Browser oder fuehren Sie `php artisan down` per SSH/Cron aus.

### Update durchfuehren

1. Laden Sie das neue Release herunter.
2. Ueberschreiben Sie alle Dateien per FTP. Ueberspringen Sie dabei **nicht** die `.env`-Datei — diese wird beim Upload ohnehin nicht ueberschrieben, solange Sie nur die Release-Dateien hochladen.
3. Fuehren Sie die Datenbank-Migration aus. Dafuer gibt es zwei Wege:
   - **Ueber den Browser:** Oeffnen Sie `https://ihre-domain.de/admin/settings` und pruefen Sie, ob eine Update-Migration angeboten wird.
   - **Per Cron/SSH:** `php artisan migrate --force`
4. Leeren Sie die Caches: `php artisan config:cache && php artisan view:cache`
5. Deaktivieren Sie den Wartungsmodus: Oeffnen Sie `https://ihre-domain.de/up` oder fuehren Sie `php artisan up` aus.

---

## 9. Backup

### Was sichern?

| Was | Wo | Wie |
|-----|----|----|
| Datenbank | MySQL-Datenbank | phpMyAdmin → Export (SQL-Format) |
| Hochgeladene Dateien | `storage/app/` | Per FTP herunterladen |
| Konfiguration | `.env`-Datei im Hauptverzeichnis | Per FTP herunterladen |

### Empfohlener Rhythmus

- **Datenbank:** woechentlich oder vor jedem Update
- **Dateien:** vor jedem Update
- **Konfiguration:** nach jeder Aenderung und vor Updates

---

## 10. Troubleshooting

### Installations-Assistent erscheint nicht

- Pruefen Sie, ob das Document-Root korrekt auf `/public` zeigt.
- Pruefen Sie, ob die `.htaccess`-Datei im `public/`-Ordner vorhanden ist.
- Stellen Sie sicher, dass `mod_rewrite` (Apache) aktiviert ist.

### Datenbankverbindung schlaegt fehl

- Pruefen Sie Host, Port, Datenbankname, Benutzername und Passwort.
- Bei Strato lautet der Host oft `rdbms.strato.de`, nicht `localhost`.
- Stellen Sie sicher, dass der Datenbankbenutzer Zugriff auf die angegebene Datenbank hat.

### Seite zeigt „500 Internal Server Error"

- Pruefen Sie die Schreibrechte: `storage/` und `bootstrap/cache/` muessen beschreibbar sein (Rechte 755 oder 775).
- Schauen Sie in `storage/logs/laravel.log` nach der Fehlermeldung.

### GPS-Daten kommen nicht an

- Pruefen Sie in OwnTracks, ob der Modus auf „HTTP" steht (nicht MQTT).
- Pruefen Sie die URL: `https://ihre-domain.de/api/owntracks/report`
- Pruefen Sie Benutzername und Passwort in der OwnTracks-App.
- Oeffnen Sie die OwnTracks-Uebersicht in Schneespur — dort wird der letzte Verbindungsstatus angezeigt.

### Wetterdaten fehlen bei Einsaetzen

- Stellen Sie sicher, dass der Cron-Job laeuft (siehe [Cron-Job einrichten](#6-cron-job-einrichten)).
- Wetterdaten werden ueber Open-Meteo abgerufen. Pruefen Sie, ob Ihr Server ausgehende HTTPS-Verbindungen erlaubt.

### E-Mails werden nicht versendet

- Pruefen Sie die SMTP-Einstellungen unter Einstellungen → E-Mail.
- Nutzen Sie die Test-E-Mail-Funktion in den Einstellungen.
- Schauen Sie in `storage/logs/laravel.log` nach Fehlermeldungen.

### Cron-Job funktioniert nicht

- Pruefen Sie den PHP-Pfad: Fuehren Sie `which php` aus oder fragen Sie Ihren Hoster.
- Pruefen Sie den Pfad zur `artisan`-Datei.
- Testen Sie den Befehl manuell: `php /pfad/zu/schneespur/artisan schedule:run`

---

## Hilfe

Bei Fragen nutzen Sie die integrierte Hilfe im Admin-Bereich (Menue → Hilfe) oder erstellen Sie ein Issue im GitHub-Repository.
