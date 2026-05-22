# Managed Hosting für Schneespur – Architektur-Design

**Datum:** 2026-05-22
**Status:** Entwurf, zur Review
**Scope:** Architektur-Spezifikation für ein Managed-Hosting-Angebot zusätzlich zum bestehenden OSS-Shared-Hosting-Vertriebsweg. Implementations-Plan folgt separat.

---

## 1. Kontext und Ziel

Schneespur wird als OSS-Projekt entwickelt und läuft heute auf klassischem Shared-Webhosting (Strato, IONOS, All-Inkl) mit Web-Installer-Assistent. Zusätzlich soll ein **Managed-Hosting-Angebot** entstehen, bei dem wir Server, SSL, Backups und DSGVO-Auftragsverarbeitung übernehmen und der Kunde nur noch sein Schneespur nutzt.

### Geschäftsmodell-Abgrenzung

Wir betreiben die **Infrastruktur**, nicht die **Applikation**. Konkret:

- **Wir übernehmen:** Server, Webserver, PHP-FPM, MySQL, Caddy-TLS, Backups, DSGVO-AVV, Initial-Setup
- **Der Kunde behält:** Updates (per Klick im Schneespur-Admin), Modul-Installationen, App-Konfiguration, Inhalte

Das hält die Erwartung sauber und vermeidet, dass Managed-Hosting in "App-Support" mutiert.

### Erfolgskriterien

1. Eine neue Bestellung führt **vollautomatisch** binnen <60 Sek von Bezahlung zu nutzbarer Instanz
2. **Strikte Mandanten-Isolation**: Kunde A kann technisch nicht auf Daten/Code/Sessions von Kunde B zugreifen
3. **Horizontal skalierbar**: Mehr Kunden ⇒ mehr Hosts hinzufügen ohne Migration bestehender Kunden
4. **OSS und Managed teilen dieselbe Codebasis**: Nichts, was wir hier bauen, soll Schneespur als OSS-Produkt schlechter machen

---

## 2. Architektur-Entscheidungen (Decision Log)

| # | Bereich | Entscheidung | Verworfen |
|---|---|---|---|
| 1 | Mandantenmodell | Verzeichnis + eigene MySQL-DB pro Kunde | Docker pro Kunde, VM pro Kunde |
| 2 | Isolation | Linux-User + PHP-FPM-Pool + MySQL-User pro Kunde | Geteilter User mit open_basedir |
| 3 | Webserver | Caddy mit Wildcard-Cert + On-Demand-TLS | Apache, Nginx |
| 4 | Bootstrap | Headless CLI `php artisan schneespur:install --managed` | Skeleton-DB-Dump-Prefab, Hybrid |
| 5 | Control-Plane | HTTP-Push Portal → Agent, HMAC-signiert | Shared Redis Queue, Worker-Polling |
| 6 | Billing/Portal | Paymenter mit eigener `SchneespurServer`-Extension | Eigenbau, Stripe+lexoffice-Hybrid |
| 7 | Releases | Über Jenni (`jenni.noschmarrn.dev`), Ed25519-Signatur-Verify | Eigener Release-Kanal |
| 8 | Updates | Per-Instanz vom Kunden (identisch OSS) | Zentral gerollt, Symlink-Swap |
| 9 | Backups | Täglich, AES-256-GCM, Off-Site Hetzner Storage Box | ZFS-Snapshots, spatie/laravel-backup pro Instanz |
| 10 | Suspend | Caddy zeigt "Gesperrt – Kontakt aufnehmen", Daten bleiben | Hard-Block FPM |
| 11 | Terminate | 30 Tage Grace, dann harte Löschung inkl. Backups | Sofort-Löschung, 90-Tage-Grace |

---

## 3. System-Topologie

### Komponenten-Übersicht

```
EXTERNE DIENSTE
  Jenni (Releases)    Hetzner Storage Box    Stripe/PayPal    SMTP

CONTROL PLANE
  ┌──────────────────────────────┐         ┌────────────────────────────┐
  │  Paymenter                   │   HMAC  │  Schneespur Agent          │
  │  (Customer Portal +          │ ──────► │  (HTTP-Service auf Host)   │
  │   Billing + SchneespurServer │ ◄────── │  /api/v1/...               │
  │   Extension)                 │ Callback│  + lokale Job-Queue        │
  └──────────────────────────────┘         └────────────────────────────┘

DATA PLANE (pro Host)
  Caddy ──► PHP-FPM-Pool c12345 ──► /home/c12345/app/public/
        ──► PHP-FPM-Pool c12346 ──► /home/c12346/app/public/
        ──► PHP-FPM-Pool c12347 ──► /home/c12347/app/public/

  MySQL-Server: DB c12345, c12346, c12347 (je eigener DB-User)

  Cron pro Kunde:   Laravel scheduler
  Cron global:      Nightly Backup → Hetzner Storage Box (verschlüsselt)
```

### Server-Aufteilung

**Tag 1:**
- Paymenter auf separatem Mini-VPS (~5 €/Monat, z. B. Hetzner CX11)
- Erster Schneespur-Host auf eigenem VPS (~20-50 €/Monat, z. B. Hetzner CCX13/23)

Begründung der Trennung: Portal überlebt Host-Probleme, Host kann ohne Bestellunterbrechung getauscht werden, sauberer Sicherheitsperimeter.

**Skalierung:**
- Wenn Host 1 voll: weiterer Host bereitgestellt (via Ansible/Bash-Provisionierung), in Paymenter als neuer `schneespur_hosts`-Eintrag registriert
- Bestandskunden bleiben auf altem Host (kein Sharding-State)
- Neue Bestellungen werden auf Host mit freier Kapazität geleitet
- Premium-Tier später möglich als Host mit `capacity=1`

### Host-Stack

Pro Schneespur-Host:

- **Betriebssystem:** Debian/Ubuntu LTS
- **Webserver:** Caddy (Wildcard-Cert via DNS-01 für `*.schneespur-host.de`, On-Demand-TLS für externe Kundendomains)
- **PHP:** PHP 8.3+ mit FPM, ein Pool pro Kunde, Pool läuft als `c{N}`-User
- **DB:** MySQL/MariaDB (lokal oder zentral – konfigurierbar pro Host)
- **Cron:** systemd-Timer für globalen Backup-Job, Laravel-Scheduler pro Kunde
- **Agent:** schlanke Laravel-Mini-App, eigener systemd-Service, hört auf TLS-Port hinter Caddy
- **Tools:** `rclone` für Hetzner Storage Box, `mysqldump`, `useradd`/`userdel`, GPG/sodium für Signaturprüfung

---

## 4. Mandanten-Isolation

### Linux-User-Layer

Jeder Kunde bekommt einen eindeutigen Unix-User der Form `c{numerisch}`, z. B. `c12345`. Generierung: `c` + zero-padded Auto-Increment aus `schneespur_instances.id`. Der Name leitet sich bewusst **nicht** aus Customer-Daten ab (kein E-Mail-Prefix, kein Firmenname) – das hält die ID stabil bei Customer-Renames und enthält keine personenbezogenen Daten in Dateipfaden. Die Sequenz-Natur (`c12345` impliziert, dass `c12344` existiert) ist hier unkritisch, weil Verzeichnis-Permissions die Trennung erzwingen, nicht die Unrate-Barkeit des Namens.

```
/home/c12345/
  ├── app/                    ← Schneespur-Code (App-Root)
  │   ├── public/             ← Document-Root für Caddy
  │   ├── storage/            ← User-Uploads, Logs, Cache
  │   ├── .env                ← DB-Credentials, App-Key, Mail-Config
  │   └── ...
  ├── logs/                   ← Pool-spezifische FPM- und Caddy-Logs
  └── tmp/                    ← FPM tmp_dir + session.save_path (isoliert)
```

Verzeichnis-Rechte: `/home/c12345/` gehört `c12345:c12345` mit Mode `0750`. Andere User können nicht hineinsehen.

### PHP-FPM-Layer

Pro Kunde eigener FPM-Pool unter `/etc/php/8.3/fpm/pool.d/c12345.conf`:

```ini
[c12345]
user = c12345
group = c12345
listen = /run/php/c12345.sock
listen.owner = www-data       ; nur Caddy darf zugreifen
listen.group = www-data
listen.mode = 0660

php_admin_value[upload_tmp_dir] = /home/c12345/tmp
php_admin_value[session.save_path] = /home/c12345/tmp
php_admin_value[sys_temp_dir] = /home/c12345/tmp
php_admin_value[open_basedir] = /home/c12345

pm = ondemand                  ; idle = 0 RAM
pm.max_children = 8
pm.process_idle_timeout = 60s
```

**`pm = ondemand`** ist wichtig für Dichte: Pools verbrauchen idle 0 MB, erst bei Request startet ein PHP-Worker.

### MySQL-Layer

Pro Kunde eigene DB und eigener DB-User:

```sql
CREATE DATABASE c12345 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'c12345'@'localhost' IDENTIFIED BY '<random>';
GRANT ALL PRIVILEGES ON c12345.* TO 'c12345'@'localhost';
FLUSH PRIVILEGES;
```

DB-User hat **nur** Rechte auf die eigene DB, kein `GRANT`, kein `FILE`, kein `SUPER`.

### Caddy-Layer

Pro Kunde eigenes Snippet unter `/etc/caddy/sites/c12345.caddy`:

```
kunde123.schneespur-host.de, winterdienst-mueller.de {
    root * /home/c12345/app/public
    php_fastcgi unix//run/php/c12345.sock
    encode gzip
    file_server
    log {
        output file /home/c12345/logs/access.log
    }
}
```

Haupt-`Caddyfile` enthält `import /etc/caddy/sites/*.caddy`.

---

## 5. Agent-API-Vertrag

### Authentifizierung

Jeder Request trägt vier Header:

```
X-Schneespur-Key-Id:    host01-key-2026-a
X-Schneespur-Timestamp: 1748956800
X-Schneespur-Nonce:     7f8a9b2c-...
X-Schneespur-Signature: hex(hmac-sha256(secret,
                            METHOD || PATH || TIMESTAMP || NONCE || sha256(BODY)))
```

- **Key-ID + Secret-Rotation** möglich (Agent akzeptiert während Rotation alte und neue Keys)
- **Timestamp ± 5 Minuten** Toleranz gegen Clock-Skew
- **Nonce-Tabelle in Agent-SQLite** mit TTL 10 Min für Replay-Schutz
- **TLS Pflicht**, auch intern (Caddy macht das ohnehin)

### Endpoint-Katalog

| Endpoint | Methode | Zweck | Modus |
|---|---|---|---|
| `/api/v1/instances` | POST | Neue Instanz provisionieren | Async (202 + Callback) |
| `/api/v1/instances/{id}` | GET | Status, Disk, Version | Sync |
| `/api/v1/instances/{id}` | PATCH | Plan ändern (Quotas) | Sync |
| `/api/v1/instances/{id}/suspend` | POST | Sperren | Sync |
| `/api/v1/instances/{id}/unsuspend` | POST | Entsperren | Sync |
| `/api/v1/instances/{id}` | DELETE | Terminieren | Sync |
| `/api/v1/instances/{id}/domains` | POST | Custom-Domain hinzufügen | Sync |
| `/api/v1/instances/{id}/domains/{fqdn}` | DELETE | Custom-Domain entfernen | Sync |
| `/api/v1/instances/{id}/backups` | POST | Ad-hoc-Backup auslösen | Async |
| `/api/v1/instances/{id}/backups` | GET | Backup-Liste | Sync |
| `/api/v1/instances/{id}/backups/{bid}/restore` | POST | Restore (Support-Operation) | Async |
| `/api/v1/instances/{id}/magic-link` | POST | Frischen Magic-Link | Sync |
| `/api/v1/host` | GET | Host-Metriken | Sync |
| `/api/v1/tls/ask` | GET | On-Demand-TLS-Erlaubnis (von Caddy intern) | Sync |

**Bewusst nicht enthalten:** Schneespur-Update-Endpoint, Modul-Install-Endpoint, Log-Stream-Endpoint, Metrics-Pull-Endpoint. Updates und Module sind Kunden-Self-Service via Schneespur-Admin; Logs/Metrics gehören in Loki/Prometheus, nicht in den Agent.

### Async-Pattern (Provisioning)

```
Paymenter ─► Agent:  POST /api/v1/instances
                     { idempotency_key, service_id, plan, domain, admin,
                       branding, callback_url, callback_secret }

Agent ─► Paymenter:  202 { job_id, status: "queued" }

[Agent arbeitet ~15-25 Sek...]

Agent ─► Paymenter:  POST {callback_url}
                     { job_id, status: "succeeded", instance_id, magic_link }
```

**Idempotency-Key** (Stripe-Pattern): Gleicher Key ⇒ Agent gibt vorherige Antwort zurück, statt ein zweites Mal zu provisionieren. Wichtig gegen Retries bei Timeouts.

### Beispiel-Payload (POST /instances)

```json
{
  "idempotency_key": "paymenter-service-4711-attempt-1",
  "service_id": 4711,
  "plan": {
    "slug": "winter-s",
    "disk_quota_mb": 5000,
    "mail_sends_per_day": 200,
    "max_users": 10
  },
  "domain": {
    "type": "subdomain",
    "fqdn": "kunde123.schneespur-host.de"
  },
  "admin": {
    "email": "max@winterdienst-mueller.de",
    "name": "Max Müller",
    "locale": "de"
  },
  "branding": {
    "company_name": "Winterdienst Müller GmbH",
    "vat_id": "DE123456789"
  },
  "callback_url": "https://portal.schneespur-host.de/internal/agent-callbacks/4711",
  "callback_secret": "rotating-hmac-key-for-callback-direction"
}
```

---

## 6. Datenmodell

### Paymenter-Core (unverändert)

Wird nicht modifiziert. Wir referenzieren per FK auf `customers`, `services`, `invoices`, `transactions`, `products`, `plans`.

### Extension-Tabellen (in Paymenter-DB)

#### `schneespur_hosts`
```
id              bigint PK
slug            varchar(50) unique     -- "host01"
hostname        varchar(255)
agent_url       varchar(255)
hmac_key_id     varchar(50)
hmac_secret     text (Laravel::Crypt verschlüsselt)
capacity        int                    -- max Instanzen
status          enum('active','draining','maintenance','retired')
mysql_host      varchar(255)
notes           text
created_at, updated_at
```

#### `schneespur_instances`
```
id                  bigint PK
service_id          bigint FK paymenter.services unique
host_id             bigint FK schneespur_hosts
unix_user           varchar(20) unique  -- "c12345", stabile ID
db_name             varchar(64)
db_user             varchar(32)
status              enum('provisioning','active','suspended','terminating','terminated')
plan_snapshot       json                -- Werte zum Provisioning-Zeitpunkt
installed_version   varchar(20)
provisioned_at      timestamp nullable
suspended_at        timestamp nullable
terminate_after     timestamp nullable
admin_email         varchar(255)
admin_locale        varchar(5)
created_at, updated_at, deleted_at      -- SoftDeletes
```

#### `schneespur_instance_domains`
```
id           bigint PK
instance_id  bigint FK schneespur_instances
fqdn         varchar(255) unique
type         enum('subdomain','custom')
tls_status   enum('pending','active','failed')
tls_error    text nullable
is_primary   bool
verified_at  timestamp nullable
created_at, updated_at
```

#### `schneespur_provisioning_jobs`
```
id                bigint PK
instance_id       bigint FK nullable
type              enum('create','suspend','unsuspend','terminate',
                       'add_domain','remove_domain','backup','restore',
                       'plan_change','magic_link')
idempotency_key   varchar(64) unique
status            enum('queued','running','succeeded','failed','retryable')
request_payload   json
response_payload  json
error_message     text nullable
attempts          int default 0
created_at, completed_at
```

#### `schneespur_backups` (Cache, Source-of-Truth ist Hetzner)
```
id            bigint PK
instance_id   bigint FK
started_at    timestamp
completed_at  timestamp nullable
size_bytes    bigint nullable
storage_path  varchar(500)
status        enum('running','succeeded','failed','expired')
trigger       enum('schedule','manual','pre_action')
created_at
```

### Agent-lokal (SQLite unter `/var/lib/schneespur-agent/state.db`)

```
instances_local
  id, service_id, status, fpm_pool_path, caddy_snippet,
  install_path, installed_version, created_at, updated_at

jobs
  id, type, idempotency_key (unique), status,
  payload, result, attempts, created_at, completed_at

nonces
  nonce (PK), seen_at  -- cron: DELETE WHERE seen_at < now() - 600

backup_keys
  instance_id (PK), key_b64, created_at
  -- Datei-Permission 0600, owner root
  -- Optional zusätzlich mit Agent-Master-Key aus ENV verschlüsselt
```

### Source-of-Truth-Regeln

| Information | Quelle |
|---|---|
| Service-Status (aktiv/gekündigt) | Paymenter |
| Plan + Limits | Paymenter (`plan_snapshot`) |
| Domain-Liste | Paymenter |
| TLS-Status, Health, Disk-Auslastung | Agent |
| Existierende Backup-Dateien | Hetzner Storage Box |
| Installierte Schneespur-Version | Agent (gemeldet an Paymenter) |

Drift-Behandlung: nächtlicher `php artisan schneespur:reconcile`-Job in Paymenter vergleicht und korrigiert.

---

## 7. Lifecycle-Sequenzen

### 7.1 Order → Provisioning

1. Kunde bestellt in Paymenter, zahlt via Stripe-Checkout
2. Stripe-Webhook → Paymenter setzt Service auf `active`
3. Paymenter feuert Event `service.created`
4. `SchneespurServer`-Extension reagiert: wählt Host (lowest `current_instances` mit `status=active` und `current_instances < capacity`), legt `schneespur_instances`-Zeile mit Status `provisioning` an. **Falls kein Host frei ist:** Job auf `status='retryable'`, Admin-Alert wird ausgelöst, Kunden-Mail "Setup verzögert, du wirst informiert sobald deine Instanz bereit ist". Kein Stornieren der Bestellung – Zahlung ist erfolgt, wir liefern eben mit Verzögerung.
5. Extension sendet `POST /api/v1/instances` an Agent (HMAC + Idempotency-Key)
6. Agent quittiert mit `202`, queued lokalen Provisioning-Job
7. Agent führt Job aus:
   - `useradd c12345`, Verzeichnisstruktur anlegen
   - MySQL: `CREATE DATABASE c12345`, `CREATE USER`, `GRANT`
   - Caddy-Snippet schreiben + `caddy reload`
   - PHP-FPM-Pool-Konfig schreiben + `systemctl reload php-fpm`
   - `GET https://jenni.noschmarrn.dev/api/projects/schneespur/latest`
   - ZIP herunterladen (mit Cache unter `/var/cache/schneespur-agent/releases/`)
   - Ed25519-Signaturprüfung
   - Entpacken nach `/home/c12345/app/`, `chown -R c12345:c12345`
   - `.env` schreiben (DB-Creds, APP_KEY generieren, Mail-Config)
   - `sudo -u c12345 php artisan schneespur:install --managed --admin-email=...`
   - Magic-Link-Token erhalten
8. Agent postet Callback an Paymenter mit `{magic_link, instance_id, installed_version}`
9. Paymenter aktualisiert `schneespur_instances` auf `status=active`
10. Paymenter versendet Willkommens-E-Mail mit Magic-Link an Kunden

**Erwartete Gesamtdauer:** 20-40 Sek (Stripe-Webhook bis Mail-Versand).

**Failure-Modes:**
- *Stripe-Webhook fehlt:* Paymenter pollt Stripe für offene Sessions (Standardverhalten)
- *Agent nicht erreichbar:* Job auf `retryable`, Laravel-Queue retried in 1/5/30 Min, dann Support-Mail
- *Provisioning bricht ab:* Agent macht vollständiges Rollback (`userdel`, `DROP DATABASE`, Cleanup)
- *Callback verloren:* Agent retried mit Exponential Backoff bis 24h; zusätzlich Paymenter-Poller, der nach 2 Min `GET /instances/{id}` für Jobs im Status `provisioning` ruft
- *Doppel-Webhook:* `service_id` ist unique in `schneespur_instances`, zweites Insert wird abgelehnt

### 7.2 Suspend (Rechnung überfällig)

Trigger: Paymenter-Cron erkennt überfällige Rechnung (z. B. >7 Tage).

1. Paymenter feuert `service.suspend`
2. Extension ruft `POST /api/v1/instances/{id}/suspend` am Agent
3. Agent:
   - Schreibt `/home/c12345/app/storage/app/SUSPENDED` (Defense-in-Depth-Marker)
   - Ersetzt Caddy-Snippet durch statische "Gesperrt – bitte Kontakt aufnehmen"-Seite
   - `caddy reload`
   - Pausiert Laravel-Scheduler für diesen User (Cron-Tab entfernen oder systemd-Timer stoppen)
   - PHP-FPM-Pool bleibt aktiv (idle = 0 RAM bei `pm=ondemand`)
4. Paymenter aktualisiert Status auf `suspended`, versendet Sperr-Mail mit Kontaktinfo

**Suspend-Statusseite:** Statisches HTML mit Logo, Wortlaut "Dieser Account ist gesperrt. Bitte nehmen Sie Kontakt mit uns auf: support@…", prominente Anzeige des `c12345`-Identifiers.

### 7.3 Unsuspend

1. Paymenter feuert `service.unsuspend` (z. B. nach Zahlungseingang)
2. Extension ruft `POST /api/v1/instances/{id}/unsuspend`
3. Agent:
   - Entfernt `SUSPENDED`-Marker
   - Restauriert ursprüngliches Caddy-Snippet
   - `caddy reload`
   - Reaktiviert Laravel-Scheduler
4. Innerhalb von ~5 Sekunden ist die Instanz wieder voll funktional

### 7.4 Custom-Domain hinzufügen

1. Kunde fügt Domain im Paymenter-Portal hinzu
2. Paymenter prüft DNS: CNAME `winterdienst-mueller.de → kunde123.schneespur-host.de`
3. Bei DNS-Match: `POST /api/v1/instances/{id}/domains {fqdn}` am Agent
4. Agent erweitert Caddy-Snippet um neue Domain, `caddy reload`
5. Agent ergänzt Domain in interner On-Demand-TLS-Whitelist
6. Bei nächstem HTTP-Request auf die neue Domain:
   - Caddy fragt internen `GET /api/v1/tls/ask?domain=...` → 200
   - Caddy holt Let's Encrypt-Cert via ACME
   - Request wird beantwortet

**On-Demand-TLS-Schutz:** Der `/tls/ask`-Endpoint verhindert, dass Beliebige durch CNAME-Pointing Let's-Encrypt-Rate-Limits triggern.

### 7.5 Terminate (Kündigung)

**T+0 (Kunde kündigt im Portal):**
1. Paymenter setzt Service auf `pending_termination`
2. Triggert intern Suspend-Flow (7.2)
3. Setzt `schneespur_instances.terminate_after = now() + 30 days`
4. Mail an Kunden: 30 Tage Grace, letztes Backup im Portal verfügbar

**T+30 (Paymenter-Cron prüft täglich):**
1. Findet Instanzen mit `terminate_after < now()`
2. Sendet `DELETE /api/v1/instances/{id}` an Agent
3. Agent (in dieser Reihenfolge):
   - PHP-FPM-Pool stoppen (`systemctl reload`)
   - `DROP DATABASE c12345`, `DROP USER`
   - `rm -rf /home/c12345`
   - Caddy-Snippet löschen, `caddy reload`
   - FPM-Pool-Konfig löschen, `systemctl reload php-fpm`
   - `DELETE FROM backup_keys WHERE instance_id='c12345'` ← Backups ab jetzt nicht mehr entschlüsselbar
   - `rclone delete hetzner:schneespur-backups/c12345/`
4. Paymenter-Eintrag wird soft-deleted (Audit-Trail bleibt 10 Jahre wegen GoBD), Domains und SoftDelete-Zeile bleiben für forensische Nachverfolgung

**GoBD-Hinweis:** Bezahlte Rechnungen in Paymenter (`invoices`, `transactions`) bleiben unverändert 10 Jahre erhalten. Wir löschen nur operative Daten.

### 7.6 Nightly Backup

Trigger: Cron auf Host, Startzeit 03:00 + Jitter pro Instanz (0-600 Sek), für jede Instanz mit `status=active`:

1. `mysqldump c12345` → `/tmp/c12345-<datum>.sql`
2. `tar /home/c12345/` → `/tmp/c12345-<datum>.tar`
3. Zusammenführen + zstd-Komprimierung
4. AES-256-GCM-Verschlüsselung mit `backup_keys[c12345]`
5. `rclone copy` → `hetzner:schneespur-backups/c12345/<datum>.zst.enc`
6. Callback an Paymenter: `schneespur_backups`-Eintrag aktualisieren
7. Retention: behalte 7 tägliche + 4 wöchentliche; alles ältere wird gelöscht

**Wöchentlicher Restore-Test:**
- Random-Instanz auswählen
- Backup herunterladen, entschlüsseln, entpacken
- Leere MySQL-DB anlegen, dump importieren
- `php artisan schneespur:doctor` ausführen (App-Health-Check)
- Bei Fehler: Alert-Mail an Admin

---

## 8. Sicherheits-Modell

### Defense-in-Depth-Layer (von außen nach innen)

1. **Caddy-TLS** – kein Klartext-Traffic, automatische Cert-Erneuerung
2. **Caddy → FPM via Unix-Socket** – kein Netzwerk-Listener für PHP, FPM nur über lokalen Socket erreichbar
3. **PHP-FPM `open_basedir`** – PHP-Prozesse können nicht aus `/home/c12345/` herausschreiben/-lesen
4. **Linux-User-Permissions** – `/home/c12345/` Mode 0750, owner `c12345:c12345`. Andere FPM-Pools sehen sich nicht
5. **MySQL-Privilege-Separation** – DB-User hat nur Rechte auf eigene DB, keine `GRANT`/`FILE`/`SUPER`
6. **Defense-in-Depth Suspend-Marker** – Schneespur-Code prüft Existenz von `storage/app/SUSPENDED` zusätzlich zur Caddy-Sperre
7. **Backup-Verschlüsselung** – AES-256-GCM mit Per-Instanz-Key, dazu optional Master-Key-Wrap via Agent-ENV

### HMAC-Auth-Details

- HMAC-SHA256 über `METHOD || PATH || TIMESTAMP || NONCE || sha256(BODY)`
- Key-Rotation per Key-ID (Agent akzeptiert Übergangsperioden mehrere Keys)
- Timestamp ± 5 Min, Nonce-Cache 10 Min in Agent-SQLite
- Separater `callback_secret` pro Provisioning-Auftrag für Agent → Paymenter-Richtung

### DSGVO

- **Auftragsverarbeitungsvertrag** mit Kunden Pflicht (Hosting = AV)
- **Datenexport via Backup**: Kunde kann letztes Backup im Portal herunterladen (verschlüsselt mit kundenspezifischem Key, der ihm per Magic-Link zugänglich gemacht wird)
- **Right-to-be-forgotten**: bei Terminate wird `backup_keys`-Eintrag gelöscht → existierende Backups sind technisch unlesbar, auch falls Hetzner sie noch in Cold-Storage hält
- **Audit-Logs**: alle Lifecycle-Operationen werden in `schneespur_provisioning_jobs` festgehalten

---

## 9. Operational Concerns

### Logging

- **Paymenter-Logs**: Standard Laravel (`storage/logs/laravel.log`) + Extension-spezifische Channel `schneespur-managed`
- **Agent-Logs**: Eigene Channel pro Endpoint, JSON-strukturiert, an Loki streambar
- **Pro-Kunde-Logs**: `/home/c12345/logs/` für Caddy-Access + FPM-Errors – auch für Support-Zugriff via Agent-Endpoint nutzbar (Future)

### Monitoring (separat, nicht im Agent)

- Prometheus-Node-Exporter pro Host
- MySQL-Exporter
- Health-Probes auf jedes Schneespur (z. B. via `GET kunde123…/up`)
- Alert-Manager: Disk >85%, MySQL-Connections >80%, Caddy-Errors, Backup-Job-Failures

### Skalierungs-Indikatoren

Wann lohnt sich Host 2?

- RAM-Auslastung >70% nach Tageshoch
- Disk >70%
- `current_instances > capacity * 0.8`

### Disaster Recovery

- **Host-Totalausfall**: neuer Host bereitstellen, Agent installieren, für jeden betroffenen Kunden Restore aus Hetzner ausführen. Zeitbudget: 4-8 Stunden für 50 Kunden mit Parallelisierung.
- **Hetzner Storage Box weg**: alle Kunden haben noch ihre Live-Instanz, aber keinen Off-Site-Backup-Schutz. Sofortige neue Backup-Destination provisionieren, erstes Voll-Backup in folgender Nacht.
- **Paymenter weg**: Schneespur-Instanzen laufen normal weiter, nur Billing/Portal/Provisioning ist gestoppt. Paymenter-DB aus Backup wiederherstellen.

---

## 10. Out of Scope (Future Work)

Bewusst nicht in Tag-1-Scope:

- **Zentrale Update-Steuerung** (Pro-Tier "wir machen Updates für dich")
- **Plan-Upgrades mit Live-Migration zwischen Hosts**
- **Multi-Region** (z. B. EU vs. US)
- **Self-Service-Restore** durch Kunden
- **WHMCS-Connector** oder andere Billing-Frontends
- **Echte CPU-/RAM-Quota-Durchsetzung** via cgroups (aktuell nur Disk via XFS-Quota)
- **Logs-Stream-Endpoint** im Agent (gehört in Loki)

---

## 11. Offene Fragen

1. **MySQL: lokal pro Host oder zentral?** Tag-1: lokal (einfacher Backup, kein Netzwerk-Hop). Bei mehreren Hosts ggf. zentral – dann andere Threat-Modell-Implikationen. Erstmal lokal, später re-evaluieren.
2. **Hetzner Storage Box: eine pro Host oder eine zentrale?** Vorschlag: eine zentrale Storage Box, Verzeichnis pro Host. Backup-Wiederherstellung über Host-Grenzen hinweg möglich.
3. **Versionierung der Agent-API**: Wer entscheidet, wann `/api/v2/` einzuführen? Empfehlung: solange Paymenter und Agent synchron deployed werden, reicht `/api/v1/`-Versionierung als Stabilitätsversprechen.
4. **Backup-Key-Master-Wrap**: Soll der Backup-Key zusätzlich mit einem aus Agent-ENV gezogenen Master-Key gewrapt werden, um Diebstahl der SQLite-DB allein wertlos zu machen? Empfehlung: ja, klein und billig, große Schutzwirkung.

---

## 12. Implementations-Reihenfolge (Vorschau für writing-plans)

Die folgende Reihenfolge wird im separaten Implementations-Plan detailliert ausgearbeitet:

1. **Schneespur-Refactor**: Web-Installer in Service-Klasse extrahieren, CLI-Wrapper `php artisan schneespur:install --managed` ergänzen
2. **Agent-Skelett**: Mini-Laravel-App mit HMAC-Middleware, Endpoints stubbed
3. **Provisioning-Flow End-to-End**: `POST /instances` voll funktional inkl. Rollback
4. **Paymenter-Extension**: `SchneespurServer`-Klasse mit `createServer/suspend/unsuspend/terminate`
5. **Erster Host-Setup-Script** (Ansible/Bash) für reproduzierbare Host-Provisionierung
6. **Domain-Lifecycle** + On-Demand-TLS-Ask-Endpoint
7. **Backup-Layer**: nightly Cron + AES + rclone + Restore-Test
8. **Beta-Phase**: Friends-and-Family-Kunden, manueller Support-Eingriff bei Bedarf
9. **Go-Live**: Landing-Page, AVV-Vorlage, öffentlicher Vertrieb
