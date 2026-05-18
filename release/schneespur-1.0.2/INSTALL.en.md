# Schneespur — Installation Guide

This guide describes how to install Schneespur on a standard shared web hosting plan (Strato, IONOS, All-Inkl, or similar) with PHP and MySQL. SSH and Docker are **not** required.

---

## Table of Contents

1. [System Requirements](#1-system-requirements)
2. [Upload Files](#2-upload-files)
3. [Configure Document Root](#3-configure-document-root)
4. [Create Database](#4-create-database)
5. [Installation Wizard](#5-installation-wizard)
6. [Set Up Cron Job](#6-set-up-cron-job)
7. [Set Up OwnTracks](#7-set-up-owntracks)
8. [Update Instructions](#8-update-instructions)
9. [Backup](#9-backup)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 8.2 | 8.3 or 8.4 |
| MySQL | 5.7 | 8.0+ |
| MariaDB (alternative) | 10.3 | 10.6+ |

### Required PHP Extensions

**Mandatory** (installation will fail without these):

- `pdo_mysql`
- `gd`

**Recommended** (warnings in the wizard if missing):

- `mbstring`
- `openssl`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`

> Most shared hosting providers have all of the above extensions enabled by default.

### Additional Requirements

- FTP or file manager access to your web space
- A MySQL/MariaDB database (provided by your hosting plan)
- The document root must be configurable to point to a subdirectory (`/public`)

---

## 2. Upload Files

1. Download the latest Schneespur release (ZIP archive).
2. Extract the archive on your computer.
3. Upload the entire contents via FTP or your hosting provider's file manager to your web directory, e.g. `/schneespur/` or directly into the root directory.

**Folder structure after upload:**

```
/schneespur/
  app/
  bootstrap/
  config/
  database/
  lang/
  public/          <-- document root must point here
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

## 3. Configure Document Root

Your domain's document root (sometimes called "web root" or "home directory") must point to the `public/` subdirectory.

**Example:** If you uploaded the files to `/schneespur/`, set the document root to `/schneespur/public/`.

How to do this on common hosts:

- **Strato:** Package management → Domain management → Redirect/Target → Change path
- **IONOS:** Hosting → Domains → Edit document root
- **All-Inkl:** Domain settings → Folder assignment

> **Important:** If your host does not allow setting a subdirectory as the document root, move the contents of `public/` into the main directory and adjust the paths in `index.php` accordingly. The installation wizard does not help with this — contact your host's support if unsure.

---

## 4. Create Database

Create a new MySQL database through your hosting provider's control panel. Make note of:

- **Host** (e.g. `localhost` or `rdbms.strato.de`)
- **Port** (default: `3306`)
- **Database name**
- **Username**
- **Password**

You will need these in the next step.

---

## 5. Installation Wizard

Open your domain in a browser. Schneespur automatically detects that no installation exists and starts the wizard.

### Step 1: Welcome

The wizard checks basic requirements and creates the configuration file (`.env`) along with the application key (`APP_KEY`).

### Step 2: Database

Enter the database credentials from Step 4. The wizard tests the connection before proceeding.

> If the `.env` file is not writable (rare on shared hosting), the wizard displays instructions for manual editing via FTP.

### Step 3: System Check

The wizard verifies the PHP version, extensions, and write permissions on key directories (`storage/`, `bootstrap/cache/`). Missing extensions are flagged as mandatory or recommended.

### Step 4: Database Migration

Database tables are created automatically. This step can be retried as many times as needed without data loss.

### Step 5: Application Configuration

Configure the following:

- **App URL** (your domain, e.g. `https://schneespur.mycompany.com`)
- **Timezone** (e.g. `Europe/Berlin`)
- **Language** (`de` or `en`)

### Step 6: Storage & Caches

The wizard creates the public storage symlink (`storage:link`) and builds caches. If the symlink fails on your host, instructions for manual FTP setup are displayed.

### Step 7: Admin Account

Create your administrator account (name, email, password with at least 8 characters).

### Step 8: Email Configuration (optional)

Set up SMTP so Schneespur can send notifications. This step can be skipped and completed later in the settings.

### Done

After completion you will see a summary. You can now log in with your admin credentials.

---

## 6. Set Up Cron Job

Schneespur requires a cron job that runs the Laravel scheduler once per minute. This processes the job queue (e.g. fetching weather data, sending notifications).

### Cron Command

```
* * * * * /usr/local/bin/php /path/to/schneespur/artisan schedule:run >> /dev/null 2>&1
```

> **Important:** Replace `/path/to/schneespur/` with the actual path on your web space and `/usr/local/bin/php` with your host's PHP path (often `/usr/bin/php` or `/usr/bin/php8.3`).

### How to Set Up the Cron Job

- **Strato:** Package management → Cron jobs → New cron job
- **IONOS:** Hosting → Cron jobs → Create cron job
- **All-Inkl:** Tools → Cron jobs → New cron job

Set the execution interval to **every minute** or the shortest interval available.

### Why Is the Cron Job Needed?

Without the cron job, no background tasks are processed:

- Weather data is not automatically added to jobs
- Email notifications are not sent
- Scheduled tasks do not run

---

## 7. Set Up OwnTracks

OwnTracks is the GPS tracking app your drivers use to record their operations. Each driver needs the app on their smartphone.

### Quick Start

1. **Install the app:** Download OwnTracks from the App Store (iOS) or Google Play Store (Android).
2. **Generate credentials:** Log in to Schneespur as admin, open the driver list, and click "Credentials" for the respective driver. Schneespur automatically generates a username and password.
3. **Scan QR code:** The credentials page displays a QR code. The driver scans it with the OwnTracks app, and the connection is configured automatically.
4. **Manual configuration** (if the QR code does not work):
   - Mode: **HTTP**
   - URL: `https://your-domain.com/api/owntracks/report`
   - Username and password: as shown in Schneespur
5. **Test:** Open the OwnTracks overview in Schneespur. Once the driver starts the app, a green status indicator should appear.

---

## 8. Update Instructions

### Before Updating

1. Create a backup (see [Backup](#9-backup)).
2. Enable maintenance mode: Open `https://your-domain.com/down` in a browser, or run `php artisan down` via SSH/cron.

### Perform the Update

1. Download the new release.
2. Overwrite all files via FTP. Do **not** skip the `.env` file — it will not be overwritten as long as you only upload the release files.
3. Run the database migration. There are two ways:
   - **Via browser:** Open `https://your-domain.com/admin/settings` and check if an update migration is offered.
   - **Via cron/SSH:** `php artisan migrate --force`
4. Clear caches: `php artisan config:cache && php artisan view:cache`
5. Disable maintenance mode: Open `https://your-domain.com/up` or run `php artisan up`.

---

## 9. Backup

### What to Back Up

| What | Where | How |
|------|-------|----|
| Database | MySQL database | phpMyAdmin → Export (SQL format) |
| Uploaded files | `storage/app/` | Download via FTP |
| Configuration | `.env` file in the root directory | Download via FTP |

### Recommended Schedule

- **Database:** weekly or before each update
- **Files:** before each update
- **Configuration:** after each change and before updates

---

## 10. Troubleshooting

### Installation Wizard Does Not Appear

- Verify that the document root points to `/public`.
- Check that the `.htaccess` file exists in the `public/` directory.
- Make sure `mod_rewrite` (Apache) is enabled.

### Database Connection Fails

- Double-check the host, port, database name, username, and password.
- On Strato, the host is often `rdbms.strato.de`, not `localhost`.
- Ensure the database user has access to the specified database.

### Page Shows "500 Internal Server Error"

- Check write permissions: `storage/` and `bootstrap/cache/` must be writable (permissions 755 or 775).
- Check `storage/logs/laravel.log` for the error message.

### GPS Data Is Not Arriving

- In OwnTracks, make sure the mode is set to "HTTP" (not MQTT).
- Verify the URL: `https://your-domain.com/api/owntracks/report`
- Check the username and password in the OwnTracks app.
- Open the OwnTracks overview in Schneespur — it shows the last connection status.

### Weather Data Is Missing from Jobs

- Make sure the cron job is running (see [Set Up Cron Job](#6-set-up-cron-job)).
- Weather data is fetched from Open-Meteo. Check that your server allows outgoing HTTPS connections.

### Emails Are Not Being Sent

- Check the SMTP settings under Settings → Email.
- Use the test email function in the settings.
- Check `storage/logs/laravel.log` for error messages.

### Cron Job Is Not Working

- Verify the PHP path: run `which php` or ask your hosting provider.
- Verify the path to the `artisan` file.
- Test the command manually: `php /path/to/schneespur/artisan schedule:run`

---

## Help

For questions, use the built-in help in the admin area (Menu → Help) or open an issue in the GitHub repository.
