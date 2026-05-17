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

### Benötigte PHP-Erweiterungen

**Pflicht** (Installation schlägt ohne diese fehl):

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

So geht das bei gängigen Hostern:

- **Strato:** Paket-Verwaltung → Domain-Verwaltung → Umleitung/Ziel → Pfad ändern
- **IONOS:** Hosting → Domains → Document-Root bearbeiten
- **All-Inkl:** Domain-Einstellungen → Ordnerzuordnung

> **Wichtig:** Wenn Ihr Hoster keinen Unterordner als Document-Root erlaubt, verschieben Sie den Inhalt von `public/` ins Hauptverzeichnis und passen Sie die Pfade in `index.php` entsprechend an. Der Installations-Assistent hilft dabei nicht — kontaktieren Sie im Zweifel Ihren Hoster.

---

## 4. Datenbank anlegen

Erstellen Sie über das Verwaltungspanel Ihres Hosters eine neue MySQL-Datenbank. Notieren Sie sich:

- **Host** (z. B. `localhost` oder `rdbms.strato.de`)
- **Port** (Standard: `3306`)
- **Datenbankname**
- **Benutzername**
- **Passwort**

Diese Daten benötigen Sie im nächsten Schritt.

---

## 5. Installations-Assistent

Öffnen Sie Ihre Domain im Browser. Schneespur erkennt automatisch, dass noch keine Installation vorliegt, und startet den Assistenten.

### Schritt 1: Willkommen

Der Assistent prüft die Grundvoraussetzungen und erzeugt die Konfigurationsdatei (`.env`) sowie den Anwendungsschlüssel (`APP_KEY`).

### Schritt 2: Datenbank

Geben Sie die Zugangsdaten aus Schritt 4 ein. Der Assistent testet die Verbindung, bevor er fortfährt.

> Falls die `.env`-Datei nicht beschreibbar ist (selten bei Shared-Hosting), zeigt der Assistent eine Anleitung zum manuellen Bearbeiten per FTP an.

### Schritt 3: Systemcheck

Der Assistent prüft PHP-Version, Erweiterungen und Schreibrechte auf wichtige Verzeichnisse (`storage/`, `bootstrap/cache/`). Fehlende Erweiterungen werden als Pflicht oder Empfehlung markiert.

### Schritt 4: Datenbank-Migration

Die Datenbanktabellen werden automatisch angelegt. Dieser Schritt kann bei Fehlern beliebig oft wiederholt werden, ohne Datenverlust.

### Schritt 5: Anwendungskonfiguration

Legen Sie fest:

- **App-URL** (Ihre Domain, z. B. `https://schneespur.meinefirma.de`)
- **Zeitzone** (z. B. `Europe/Berlin`)
- **Sprache** (`de` oder `en`)

### Schritt 6: Speicher & Caches

Der Assistent erstellt die Verknüpfung zum öffentlichen Speicher (`storage:link`) und baut Caches auf. Falls die Verknüpfung auf Ihrem Hoster nicht funktioniert, wird eine Anleitung zum manuellen Anlegen per FTP angezeigt.

### Schritt 7: Admin-Konto

Erstellen Sie Ihr Administrator-Konto (Name, E-Mail, Passwort mit mindestens 8 Zeichen).

### Schritt 8: E-Mail-Konfiguration (optional)

Richten Sie SMTP-Versand ein, damit Schneespur Benachrichtigungen senden kann. Dieser Schritt kann übersprungen und später in den Einstellungen nachgeholt werden.

### Fertig

Nach Abschluss sehen Sie eine Zusammenfassung. Sie können sich jetzt mit Ihren Admin-Zugangsdaten anmelden.

---

## 6. Cron-Job einrichten

Schneespur benötigt einen Cron-Job, der einmal pro Minute den Laravel-Scheduler ausführt. Dieser verarbeitet die Auftragswarteschlange (z. B. Wetterdaten abrufen, Benachrichtigungen senden).

### Cron-Befehl

```
* * * * * /usr/local/bin/php /pfad/zu/schneespur/artisan schedule:run >> /dev/null 2>&1
```

> **Wichtig:** Ersetzen Sie `/pfad/zu/schneespur/` durch den tatsächlichen Pfad auf Ihrem Webspace und `/usr/local/bin/php` durch den PHP-Pfad Ihres Hosters (häufig auch `/usr/bin/php` oder `/usr/bin/php8.3`).

### So richten Sie den Cron-Job ein

- **Strato:** Paket-Verwaltung → Cron-Jobs → Neuer Cronjob
- **IONOS:** Hosting → Cron-Jobs → Cronjob anlegen
- **All-Inkl:** Tools → Cronjobs → Neuer Cronjob

Stellen Sie die Ausführung auf **jede Minute** oder das kürzeste verfügbare Intervall.

### Warum ist der Cron-Job nötig?

Ohne Cron-Job werden keine Hintergrundaufgaben verarbeitet:

- Wetterdaten werden nicht automatisch zu Einsätzen hinzugefügt
- E-Mail-Benachrichtigungen werden nicht versendet
- Geplante Aufgaben laufen nicht

---

## 7. OwnTracks einrichten

OwnTracks ist die GPS-Tracking-App, mit der Ihre Fahrer die Einsätze aufzeichnen. Jeder Fahrer benötigt die App auf seinem Smartphone.

### Kurzanleitung

1. **App installieren:** OwnTracks aus dem App Store (iOS) oder Google Play Store (Android) herunterladen.
2. **Zugangsdaten erzeugen:** Melden Sie sich als Admin in Schneespur an, öffnen Sie die Fahrer-Übersicht und klicken Sie beim jeweiligen Fahrer auf „Zugangsdaten". Schneespur erzeugt automatisch Benutzername und Passwort.
3. **QR-Code scannen:** Auf der Zugangsdaten-Seite wird ein QR-Code angezeigt. Der Fahrer scannt diesen mit der OwnTracks-App, und die Verbindung wird automatisch konfiguriert.
4. **Manuell konfigurieren** (falls QR-Code nicht funktioniert):
   - Modus: **HTTP**
   - URL: `https://ihre-domain.de/api/owntracks/report`
   - Benutzername und Passwort: wie in Schneespur angezeigt
5. **Testen:** Öffnen Sie in Schneespur unter „OwnTracks" die Übersicht. Sobald der Fahrer die App startet, sollte dort ein grüner Status erscheinen.

---

## 8. Update-Anleitung

### Vor dem Update

1. Erstellen Sie ein Backup (siehe [Backup](#9-backup)).
2. Aktivieren Sie den Wartungsmodus: Öffnen Sie `https://ihre-domain.de/down` im Browser oder führen Sie `php artisan down` per SSH/Cron aus.

### Update durchführen

1. Laden Sie das neue Release herunter.
2. Überschreiben Sie alle Dateien per FTP. Überspringen Sie dabei **nicht** die `.env`-Datei — diese wird beim Upload ohnehin nicht überschrieben, solange Sie nur die Release-Dateien hochladen.
3. Führen Sie die Datenbank-Migration aus. Dafür gibt es zwei Wege:
   - **Ueber den Browser:** Öffnen Sie `https://ihre-domain.de/admin/settings` und prüfen Sie, ob eine Update-Migration angeboten wird.
   - **Per Cron/SSH:** `php artisan migrate --force`
4. Leeren Sie die Caches: `php artisan config:cache && php artisan view:cache`
5. Deaktivieren Sie den Wartungsmodus: Öffnen Sie `https://ihre-domain.de/up` oder führen Sie `php artisan up` aus.

---

## 9. Backup

### Was sichern?

| Was | Wo | Wie |
|-----|----|----|
| Datenbank | MySQL-Datenbank | phpMyAdmin → Export (SQL-Format) |
| Hochgeladene Dateien | `storage/app/` | Per FTP herunterladen |
| Konfiguration | `.env`-Datei im Hauptverzeichnis | Per FTP herunterladen |

### Empfohlener Rhythmus

- **Datenbank:** wöchentlich oder vor jedem Update
- **Dateien:** vor jedem Update
- **Konfiguration:** nach jeder Änderung und vor Updates

---

## 10. Troubleshooting

### Installations-Assistent erscheint nicht

- Prüfen Sie, ob das Document-Root korrekt auf `/public` zeigt.
- Prüfen Sie, ob die `.htaccess`-Datei im `public/`-Ordner vorhanden ist.
- Stellen Sie sicher, dass `mod_rewrite` (Apache) aktiviert ist.

### Datenbankverbindung schlägt fehl

- Prüfen Sie Host, Port, Datenbankname, Benutzername und Passwort.
- Bei Strato lautet der Host oft `rdbms.strato.de`, nicht `localhost`.
- Stellen Sie sicher, dass der Datenbankbenutzer Zugriff auf die angegebene Datenbank hat.

### Seite zeigt „500 Internal Server Error"

- Prüfen Sie die Schreibrechte: `storage/` und `bootstrap/cache/` müssen beschreibbar sein (Rechte 755 oder 775).
- Schauen Sie in `storage/logs/laravel.log` nach der Fehlermeldung.

### GPS-Daten kommen nicht an

- Prüfen Sie in OwnTracks, ob der Modus auf „HTTP" steht (nicht MQTT).
- Prüfen Sie die URL: `https://ihre-domain.de/api/owntracks/report`
- Prüfen Sie Benutzername und Passwort in der OwnTracks-App.
- Öffnen Sie die OwnTracks-Übersicht in Schneespur — dort wird der letzte Verbindungsstatus angezeigt.

### Wetterdaten fehlen bei Einsätzen

- Stellen Sie sicher, dass der Cron-Job läuft (siehe [Cron-Job einrichten](#6-cron-job-einrichten)).
- Wetterdaten werden über Open-Meteo abgerufen. Prüfen Sie, ob Ihr Server ausgehende HTTPS-Verbindungen erlaubt.

### E-Mails werden nicht versendet

- Prüfen Sie die SMTP-Einstellungen unter Einstellungen → E-Mail.
- Nutzen Sie die Test-E-Mail-Funktion in den Einstellungen.
- Schauen Sie in `storage/logs/laravel.log` nach Fehlermeldungen.

### Cron-Job funktioniert nicht

- Prüfen Sie den PHP-Pfad: Führen Sie `which php` aus oder fragen Sie Ihren Hoster.
- Prüfen Sie den Pfad zur `artisan`-Datei.
- Testen Sie den Befehl manuell: `php /pfad/zu/schneespur/artisan schedule:run`

---

## Hilfe

Bei Fragen nutzen Sie die integrierte Hilfe im Admin-Bereich (Menü → Hilfe) oder erstellen Sie ein Issue im GitHub-Repository.
