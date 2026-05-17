<?php

return [
    // Settings index card
    'settings_title'             => 'Auto-Update',
    'settings_description'       => 'Automatische Updates und Versionsverwaltung.',

    // Settings page
    'page_title'                 => 'Auto-Update',
    'current_version'            => 'Aktuelle Version',
    'auto_check_label'           => 'Automatischer Update-Check',
    'auto_check_help'            => 'Wenn aktiviert, prüft :app_name täglich automatisch auf neue Versionen.',
    'auto_check_enabled'         => 'Aktiviert (täglich um 04:17 Uhr)',
    'auto_check_disabled'        => 'Deaktiviert',

    // Check now
    'check_now'                  => 'Jetzt prüfen',
    'checking'                   => 'Prüfe…',
    'check_result_up_to_date'    => ':app_name ist auf dem neuesten Stand.',
    'check_result_update'        => 'Update verfügbar: Version :version',
    'check_result_error'         => 'Update-Check fehlgeschlagen: :error',
    'check_result_no_release'    => 'Noch kein Release auf dem Update-Server verfügbar.',

    // Update details
    'changelog'                  => 'Änderungen',
    'released_at'                => 'Veröffentlicht',
    'download_size'              => 'Download-Größe',

    // Install
    'install_button'             => 'Update installieren',
    'installing'                 => 'Update wird installiert…',
    'install_success'            => 'Update auf Version :version erfolgreich installiert.',
    'install_failed'             => 'Update fehlgeschlagen: :error',

    // Backup
    'backup_title'               => 'Backup-Empfehlung',
    'backup_warning'             => 'Es wird dringend empfohlen, vor dem Update ein Backup Ihrer Datenbank und Dateien zu erstellen.',
    'backup_db_info'             => 'Datenbank: :host / :database',
    'backup_instructions'        => 'Erstellen Sie ein Backup über phpMyAdmin (SQL-Export) und laden Sie Ihre Dateien per FTP herunter.',
    'backup_confirm'             => 'Ich habe ein Backup erstellt',

    // Trust info
    'trust_title'                => 'Trust-Status',
    'trust_version'              => 'Trust-Version',
    'trust_expires'              => 'Gültig bis',
    'trust_keys'                 => 'Aktive Signing-Keys',
    'trust_not_loaded'           => 'Noch nicht geladen',

    // Sodium missing
    'sodium_missing'             => 'Die PHP-Erweiterung "sodium" ist nicht geladen. Auto-Updates können nicht verifiziert werden.',

    // Dashboard widget
    'dashboard_title'            => 'Update-Status',
    'dashboard_up_to_date'       => 'Aktuell',
    'dashboard_update_available' => 'Update verfügbar',
    'dashboard_never_checked'    => 'Noch nicht geprüft',
    'dashboard_last_checked'     => 'Zuletzt geprüft',
    'dashboard_version'          => 'Version',

    // Artisan output
    'artisan_up_to_date'         => 'Bereits auf neuester Version.',
    'artisan_update_available'   => 'Update verfügbar: :version (counter :counter, signiert :signed_at)',
    'artisan_apply_hint'         => '--apply um ZIP zu laden + zu verifizieren.',
    'artisan_zip_verified'       => 'ZIP verifiziert: :path',
    'artisan_check_failed'       => 'Update-Check fehlgeschlagen: :error',
    'artisan_zip_failed'         => 'ZIP-Verify fehlgeschlagen: :error',

    // Preflight
    'preflight_title'            => 'Voraussetzungen',
    'preflight_ok'               => 'Alle Voraussetzungen erfüllt.',
    'preflight_fail'             => 'Nicht alle Voraussetzungen erfüllt:',
    'preflight_sodium'           => 'PHP-Erweiterung sodium',
    'preflight_zip'              => 'PHP-Erweiterung zip',
    'preflight_writable'         => 'Verzeichnis schreibbar',
    'preflight_disk_space'       => 'Ausreichend Speicherplatz',

    // Recovery
    'recovery_no_info'           => 'Keine Recovery-Informationen vorhanden.',
    'recovery_still_maintenance' => 'Achtung: Die Anwendung ist noch im Wartungsmodus.',
    'recovery_confirm_up'        => 'Wartungsmodus beenden?',
    'recovery_maintenance_disabled' => 'Wartungsmodus deaktiviert.',
    'recovery_found'             => 'Fehlgeschlagenes Update gefunden:',
    'recovery_steps_title'       => 'Empfohlene Schritte:',
    'recovery_confirm_restore'   => 'Backup wiederherstellen?',
    'recovery_restore_success'   => 'Backup erfolgreich wiederhergestellt.',
    'recovery_restore_failed'    => 'Backup-Wiederherstellung fehlgeschlagen.',
    'recovery_no_backup'         => 'Backup-Verzeichnis nicht gefunden — manuelle Wiederherstellung erforderlich.',
    'recovery_cleared'           => 'Recovery-Informationen bereinigt.',
];
