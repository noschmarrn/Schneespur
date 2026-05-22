<?php

return [
    'job_completed_subject' => 'Einsatznachweis: :customer am :date',
    'job_completed_updated_subject' => 'Aktualisierter Einsatznachweis: :customer am :date',
    'greeting' => 'Guten Tag :name,',
    'job_completed_body' => 'am :date wurde ein Einsatz (:type) für Sie durchgeführt. Zeitraum: :time_start – :time_end Uhr. Fahrer: :driver.',
    'weather_summary' => 'Wetter: :temperature°C, :precipitation mm Niederschlag',
    'weather_unavailable' => 'Wetterdaten waren zum Zeitpunkt des Einsatzes nicht verfügbar.',
    'weather_update_note' => 'Dies ist ein aktualisierter Einsatznachweis mit nun verfügbaren Wetterdaten.',
    'pdf_attached' => 'Den vollständigen Einsatznachweis finden Sie im Anhang als PDF.',
    'pdf_too_large' => 'Der Einsatznachweis ist zu groß für einen E-Mail-Anhang. Sie können ihn im System herunterladen.',
    'regards' => 'Mit freundlichen Grüßen',
    'date_format' => 'd.m.Y',

    'customer_report_subject' => 'Sammel-Einsatznachweis: :customer (:from – :to)',
    'customer_report_greeting' => 'Guten Tag :name,',
    'customer_report_body' => 'anbei erhalten Sie den Sammel-Einsatznachweis für den Zeitraum :from bis :to.',
    'customer_report_object_body' => 'anbei erhalten Sie den Sammel-Einsatznachweis für das Objekt :object im Zeitraum :from bis :to.',
    'customer_report_pdf_attached' => 'Den vollständigen Sammel-Einsatznachweis finden Sie im Anhang als PDF.',
    'customer_report_pdf_too_large' => 'Der Sammel-Einsatznachweis ist zu groß für einen E-Mail-Anhang. Bitte laden Sie ihn im System herunter.',

    'customer_report_email_sent' => 'Der Sammel-Einsatznachweis wird per E-Mail versendet.',
    'customer_report_email_duplicate' => 'Dieser Bericht wurde in den letzten 5 Minuten bereits versendet. Bitte warten Sie einen Moment.',
    'customer_report_email_no_email' => 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.',

    // Notification Log
    'page_notification_log' => 'Benachrichtigungs-Log',
    'filter_status' => 'Status',
    'filter_type' => 'Typ',
    'filter_date_from' => 'Datum von',
    'filter_date_to' => 'Datum bis',
    'filter_btn' => 'Filtern',
    'filter_reset' => 'Zurücksetzen',
    'filter_all' => 'Alle',
    'col_date' => 'Datum',
    'col_customer' => 'Kunde',
    'col_recipient' => 'Empfänger',
    'col_type' => 'Typ',
    'col_status' => 'Status',
    'col_error' => 'Fehler',
    'status_sent' => 'Gesendet',
    'status_failed' => 'Fehlgeschlagen',
    'status_skipped' => 'Übersprungen',
    'type_job_completed' => 'Einsatz abgeschlossen',
    'type_customer_report' => 'Kundenbericht',
    'empty_log' => 'Keine Benachrichtigungen vorhanden.',

    // Settings cards
    'settings_card_email' => 'E-Mail-Einstellungen',
    'settings_card_email_desc' => 'SMTP-Server und Absenderadresse konfigurieren.',
    'settings_card_log' => 'Benachrichtigungs-Log',
    'settings_card_log_desc' => 'Alle gesendeten und fehlgeschlagenen E-Mails einsehen.',

    // Email settings
    'page_email_settings' => 'E-Mail-Einstellungen',
    'smtp_section' => 'SMTP-Konfiguration',
    'sender_section' => 'Absender',
    'test_email_section' => 'Test-E-Mail',

    // Field labels
    'field_mail_mailer' => 'Mailer',
    'field_mail_host' => 'SMTP-Host',
    'field_mail_port' => 'SMTP-Port',
    'field_mail_scheme' => 'Verschlüsselung',
    'field_mail_username' => 'Benutzername',
    'field_mail_password' => 'Passwort',
    'field_mail_from_address' => 'Absender-Adresse',
    'field_mail_from_name' => 'Absender-Name',
    'password_placeholder_help' => 'Leer lassen um das bestehende Passwort beizubehalten.',
    'hint_mail_host' => 'z.B. smtp.strato.de, smtp.ionos.de, smtp.gmail.com',
    'hint_mail_port' => '587 (STARTTLS, empfohlen) oder 465 (SSL)',

    // Scheme options
    'scheme_none' => 'Keine',
    'scheme_auto' => 'Automatisch (empfohlen)',
    'scheme_starttls' => 'STARTTLS — Port 587',
    'scheme_ssl' => 'SSL — Port 465',

    // Test email
    'test_email_btn' => 'Test-E-Mail senden',
    'test_email_subject' => ':app_name Test-E-Mail',
    'test_email_body' => 'Dies ist eine Test-E-Mail von :app_name. Ihre SMTP-Konfiguration funktioniert korrekt.',
    'test_email_help' => 'Eine Test-E-Mail wird an :email gesendet.',
    'test_email_help_configurable' => 'Senden Sie eine Test-E-Mail, um die SMTP-Konfiguration zu prüfen.',
    'test_email_recipient' => 'Empfänger-Adresse',
    'test_email_success' => 'Test-E-Mail wurde erfolgreich gesendet.',
    'test_email_sent_to' => 'Test-E-Mail wurde an den SMTP-Server übergeben (Empfänger: :email). Bitte Posteingang prüfen — bei Fehlzustellung erhalten Sie eine Bounce-Mail.',
    'test_email_failed' => 'Test-E-Mail fehlgeschlagen',

    // Save / env messages
    'email_saved' => 'E-Mail-Einstellungen gespeichert.',
    'env_not_writable' => 'Die .env-Datei ist nicht beschreibbar.',
    'env_copy_instructions' => 'Kopieren Sie die folgende Konfiguration und fügen Sie sie manuell in Ihre .env-Datei ein.',
    'env_copy_btn' => 'In Zwischenablage kopieren',
    'env_copied' => 'Kopiert!',
    'env_recheck' => 'Erneut prüfen',

    // Portal credentials email
    'portal_credentials_subject'          => 'Ihr Portal-Zugang',
    'portal_credentials_reset_subject'    => 'Ihr neues Portal-Passwort',
    'portal_credentials_body'             => 'für Sie wurde ein Zugang zum Kundenportal eingerichtet. Mit den folgenden Zugangsdaten können Sie sich anmelden:',
    'portal_credentials_reset_body'       => 'Ihr Portal-Passwort wurde zurückgesetzt. Ihre neuen Zugangsdaten lauten:',
    'portal_credentials_email_label'      => 'E-Mail:',
    'portal_credentials_password_label'   => 'Passwort:',
    'portal_credentials_change_hint'      => 'Bitte ändern Sie Ihr Passwort nach der ersten Anmeldung.',
    'portal_credentials_login_button'     => 'Zum Kundenportal',
    'type_portal_credentials'             => 'Portal-Zugangsdaten',

    // Mail layout (header/footer)
    'mail_footer_phone_label' => 'Tel:',
    'mail_footer_rights'      => 'Alle Rechte vorbehalten.',
];
