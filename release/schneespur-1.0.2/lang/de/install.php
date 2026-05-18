<?php

return [
    // Step titles
    'title_step_1'                   => 'Willkommen bei :app_name',
    'title_step_2'                   => 'System-Voraussetzungen',
    'title_step_3'                   => 'Datenbank verbinden',
    'title_step_4'                   => 'Datenbank-Migrationen',
    'title_step_5'                   => 'App-Grundkonfiguration',
    'title_step_6'                   => 'Storage & Caches',
    'title_step_7'                   => 'Admin-Zugang anlegen',
    'title_step_8'                   => 'Optional: Test-E-Mail versenden',
    'title_step_9'                   => 'Cron-Job einrichten',
    'title_done'                     => 'Installation abgeschlossen',

    // Stepper
    'stepper_step_1'                 => 'Willkommen',
    'stepper_step_2'                 => 'Voraussetzungen',
    'stepper_step_3'                 => 'Datenbank',
    'stepper_step_4'                 => 'Migrationen',
    'stepper_step_5'                 => 'Konfiguration',
    'stepper_step_6'                 => 'Storage & Caches',
    'stepper_step_7'                 => 'Admin-Zugang',
    'stepper_step_8'                 => 'Test-E-Mail',
    'stepper_step_9'                 => 'Cron-Job',
    'stepper_mobile'                 => 'Schritt :current von 9: :title',

    // Buttons (shared)
    'btn_finalize'                   => 'Installation abschließen',
    'btn_retry_migration'            => 'Erneut versuchen',
    'btn_copy_error'                 => 'Fehlermeldung kopieren',
    'btn_back'                       => 'Zurück',
    'btn_continue'                   => 'Weiter',
    'btn_skip'                       => 'Überspringen',

    // Step 1: Welcome
    'welcome_intro'                  => 'Willkommen beim :app_name-Installationsassistenten.',
    'welcome_description'            => 'In wenigen Schritten richten Sie Ihre Winterdienst-Dokumentation ein. Der Assistent führt Sie durch Datenbankverbindung, Systemprüfung, Konfiguration und die Einrichtung Ihres Admin-Zugangs.',
    'welcome_steps_heading'          => 'Die Installation umfasst folgende Schritte:',
    'welcome_system_info'            => 'Systeminformationen',
    'welcome_server'                 => 'Server',
    'welcome_start_btn'              => 'Installation starten',
    'welcome_key_generated'          => 'Anwendungsschlüssel wurde erfolgreich generiert.',

    // Step 2: Database
    'db_host_label'                  => 'Datenbank-Host',
    'db_port_label'                  => 'Port',
    'db_name_label'                  => 'Datenbankname',
    'db_user_label'                  => 'Benutzername',
    'db_pass_label'                  => 'Passwort',
    'db_submit_btn'                  => 'Verbindung testen & speichern',
    'db_test_success'                => 'Datenbankverbindung erfolgreich hergestellt.',

    // Step 3: Preflight
    'preflight_heading'              => ':app_name prüft, ob alle Systemvoraussetzungen erfüllt sind.',
    'preflight_all_passed'           => 'Alle Voraussetzungen sind erfüllt. Sie können fortfahren.',
    'preflight_has_warnings'         => 'Es gibt Warnungen, die Installation kann aber fortgesetzt werden.',
    'preflight_has_failures'         => 'Kritische Voraussetzungen sind nicht erfüllt. Bitte beheben Sie die markierten Punkte, bevor Sie fortfahren.',
    'preflight_continue_btn'         => 'Weiter',

    // Step 4: Migrations
    'migration_heading'              => 'Die Datenbanktabellen werden jetzt erstellt. Dieser Vorgang kann einige Sekunden dauern.',
    'migration_run_btn'              => 'Migrationen ausführen',
    'migration_success'              => 'Alle Datenbanktabellen wurden erfolgreich erstellt.',
    'migration_error_copied'         => 'Kopiert!',

    // Step 5: Config
    'config_url_label'               => 'App-URL',
    'config_url_help'                => 'Die vollständige URL, unter der :app_name erreichbar ist (z. B. https://schneespur.example.de)',
    'config_tz_label'                => 'Zeitzone',
    'config_tz_detected'             => 'Wird nach Möglichkeit automatisch aus Ihrem Browser vorausgewählt.',
    'config_locale_label'            => 'Sprache',
    'config_brand_hint'              => 'Die App wird als „:brand" installiert.',
    'config_submit_btn'              => 'Konfiguration speichern',

    // Step 6: Storage
    'storage_heading'                => 'Storage-Verknüpfung und Caches werden jetzt eingerichtet.',
    'storage_run_btn'                => 'Ausführen',
    'storage_link_label'             => 'Storage-Link',
    'storage_config_cache_label'     => 'Konfiguration cachen',
    'storage_view_cache_label'       => 'Views cachen',
    'storage_link_success'           => 'Storage-Verknüpfung wurde erfolgreich erstellt.',
    'storage_config_cache_success'   => 'Konfiguration wurde erfolgreich gecacht.',
    'storage_view_cache_success'     => 'Views wurden erfolgreich gecacht.',
    'storage_link_warning'           => 'Der Storage-Link konnte nicht automatisch erstellt werden. :app_name nutzt automatisch einen alternativen Auslieferungsweg — Fotos und Uploads funktionieren trotzdem.',

    // Step 7: Admin
    'admin_name_label'               => 'Name',
    'admin_email_label'              => 'E-Mail-Adresse',
    'admin_pass_label'               => 'Passwort',
    'admin_pass_confirm_label'       => 'Passwort bestätigen',
    'admin_submit_btn'               => 'Admin-Zugang anlegen',

    // Step 8: Mail
    'mail_heading'                   => 'Konfigurieren Sie den SMTP-Versand für E-Mail-Benachrichtigungen. Sie können diesen Schritt auch überspringen.',
    'mail_host_label'                => 'SMTP-Host',
    'mail_port_label'                => 'Port',
    'mail_encryption_label'          => 'Verschlüsselung',
    'mail_encryption_none'           => 'Keine',
    'mail_scheme_starttls'           => 'STARTTLS — Port 587 (Standard)',
    'mail_scheme_ssl'                => 'SSL — Port 465 (ältere Server)',
    'mail_scheme_none'               => 'Ohne Verschlüsselung (Port 25 — nur lokal)',
    'mail_user_label'                => 'Benutzername',
    'mail_pass_label'                => 'Passwort',
    'mail_from_label'                => 'Absender-Adresse',
    'mail_from_name_label'           => 'Absender-Name',
    'mail_test_recipient_label'      => 'Test-Empfänger',
    'mail_submit_btn'                => 'Test-E-Mail senden',
    'mail_skip_btn'                  => 'Diesen Schritt überspringen',
    'mail_test_body'                 => 'Dies ist eine Test-E-Mail von :brand.',
    'mail_test_subject'              => 'Test-E-Mail',
    'mail_test_success'              => 'Test-E-Mail wurde erfolgreich versendet.',
    'mail_test_error'                => 'Fehler beim Versand der Test-E-Mail.',

    // Step 9: Cron
    'cron_heading'                   => ':app_name muss regelmäßig Hintergrundaufgaben ausführen — Wetterdaten ergänzen, Benachrichtigungen senden und alte Daten löschen. Richten Sie dafür einen Cron-Job bei Ihrem Hoster ein.',
    'cron_line_label'                => 'Diese Zeile in der Cron-Verwaltung Ihres Hosters eintragen:',
    'cron_instructions_heading'      => 'Anleitung',
    'cron_step_1'                    => 'Melden Sie sich bei der Verwaltungsoberfläche Ihres Hosters an (Plesk, cPanel, Confixx o.ä.).',
    'cron_step_2'                    => 'Suchen Sie den Bereich „Geplante Aufgaben" oder „Cron-Jobs".',
    'cron_step_3'                    => 'Erstellen Sie einen neuen Cron-Job mit dem Intervall „Jede Minute" (oder dem niedrigsten verfügbaren Intervall) und der obigen Zeile als Befehl.',
    'cron_test_btn'                  => 'Jetzt einmal testen',
    'cron_test_success'              => 'Hintergrundaufgaben wurden erfolgreich ausgeführt.',
    'cron_active'                    => 'Cron-Job ist aktiv und funktioniert.',
    'cron_fallback_note'             => 'Auch ohne Cron-Job funktioniert :app_name — Wetterdaten werden dann direkt beim Speichern eines Einsatzes abgerufen. Mit Cron-Job arbeitet die App aber schneller und zuverlässiger.',
    'btn_copy'                       => 'Kopieren',
    'btn_copied'                     => 'Kopiert!',

    // Done
    'done_description'               => ':app_name wurde erfolgreich installiert. Sie können sich jetzt mit Ihrem Admin-Zugang anmelden.',
    'done_login_btn'                 => 'Zum Login',
    'done_summary_url'               => 'App-URL',
    'done_summary_admin'             => 'Admin-E-Mail',
    'done_summary_mail'              => 'E-Mail konfiguriert',
    'done_mail_yes'                  => 'Ja',
    'done_mail_no'                   => 'Nein (kann später nachgeholt werden)',

    // .env fallback
    'env_fallback_instructions'      => 'Kopieren Sie den unten stehenden Inhalt und laden Sie ihn als `.env`-Datei per FTP in das Hauptverzeichnis Ihrer :app_name-Installation hoch.',
    'env_fallback_copy_btn'          => 'In Zwischenablage kopieren',
    'env_fallback_copied'            => 'Kopiert!',
    'env_fallback_recheck'           => 'Erneut prüfen',

    // Errors
    'error_db_connection'            => 'Verbindung zur Datenbank fehlgeschlagen. Prüfen Sie Host, Datenbankname, Benutzername und Passwort.',
    'error_migration_main'           => 'Die Datenbank-Migration konnte nicht ausgeführt werden. Prüfen Sie die untenstehende Fehlermeldung und versuchen Sie es erneut.',
    'error_migration_hint'           => ':app_name kann die Migration beliebig oft wiederholen — es gehen keine Daten verloren.',
    'error_env_write'                => 'Die Konfigurationsdatei konnte nicht geschrieben werden. Kopieren Sie den unten stehenden Inhalt in Ihre `.env`-Datei und laden Sie sie per FTP hoch.',

    // Flash messages
    'flash_complete'                 => 'Installation abgeschlossen. Sie können sich jetzt anmelden.',
    'flash_migration_retry_success'  => 'Die Migration wurde erfolgreich nachgeholt.',
    'flash_test_mail'                => 'Test-E-Mail wurde an :email versendet. Prüfen Sie den Posteingang.',

    // Guard
    'already_installed'              => ':app_name ist bereits installiert.',
];
