<?php

return [
    // Betrieb & Standort
    'company_title'              => 'Betrieb & Standort',
    'company_description'        => 'Betriebsadresse, Geocoding und Kontaktdaten verwalten.',
    'company_name'               => 'Firmenname',
    'company_street'             => 'Straße & Hausnummer',
    'company_zip'                => 'PLZ',
    'company_city'               => 'Ort',
    'company_state'              => 'Bundesland / Kanton',
    'company_phone'              => 'Telefon',
    'company_email'              => 'E-Mail',
    'company_lat'                => 'Breitengrad',
    'company_lon'                => 'Längengrad',
    'company_geocode_success'    => 'Adresse erfolgreich aufgelöst.',
    'company_geocode_fail'       => 'Adresse konnte nicht aufgelöst werden. Bitte Straße, PLZ und Ort prüfen.',
    'company_geocode_manual_hint' => 'Koordinaten werden automatisch aus der Adresse ermittelt.',

    // Datenschutzbeauftragter
    'dpo_title'                  => 'Datenschutzbeauftragter',
    'dpo_help'                   => 'Kontaktdaten des Datenschutzbeauftragten oder Ansprechpartners für Datenschutzanfragen (DSGVO Art. 37). Diese Daten werden in der DSGVO-Belehrung für Fahrer angezeigt.',
    'dpo_contact'                => 'Name / Ansprechpartner',
    'dpo_contact_placeholder'    => 'z. B. Max Mustermann oder Datenschutz GmbH',
    'dpo_email'                  => 'E-Mail',

    // Saisonzeitraum
    'season_title'               => 'Saisonzeitraum',
    'season_from'                => 'Saisonstart',
    'season_to'                  => 'Saisonende',
    'season_help'                => 'Definiert den aktiven Winterdienst-Zeitraum (z. B. November–März).',

    // Alert-Schwellenwerte
    'billing_title'              => 'Abrechnung',
    'billing_increment'          => 'Abrechnungstakt beim Stundenpreis',
    'billing_increment_exact'    => 'Minutengenau',
    'billing_increment_step'     => 'Je angefangene :minutes Minuten',
    'report_show_amounts'        => 'Beträge im Sammel-Nachweis zeigen',
    'report_show_amounts_help'   => 'Der Sammel-Nachweis geht an den Kunden. Eingeschaltet steht dort die Summe der Einsätze und der Pauschalen im Zeitraum. Die Auswertung und der CSV-Export zeigen Beträge immer.',
    'billing_increment_help'     => 'Gilt für Objekte mit einem Preis pro Stunde. Der Takt wird mit dem Betrag am Einsatz festgehalten; eine Änderung wirkt nur auf künftige Einsätze.',
    'alert_title'                => 'Alert-Schwellenwerte',
    'alert_overdue_hours'        => 'Überfällig nach (Stunden)',
    'alert_overdue_hours_help'   => 'Einsätze, die länger als diese Stundenzahl offen sind, werden als überfällig markiert.',

    // Locale
    'locale_title'               => 'Sprache & Format',
    'locale_default'             => 'Standard-Sprache',
    'locale_help'                => 'Legt die Anzeigesprache und das Datumsformat fest.',
    'timezone_label'             => 'Zeitzone',
    'timezone_help'              => 'Bestimmt, in welcher Zeit Einsätze eingegeben und angezeigt werden. Gespeichert wird unverändert in UTC.',
    'timezone_invalid'           => 'Die gewählte Zeitzone ist keine gültige Kennung.',
    'brand_label'                => 'Produktname',
    'brand_help'                 => 'Name, unter dem die Software in Oberfläche, PWA und E-Mails erscheint. Wird bei der Installation aus der Sprache abgeleitet (Deutsch → Schneespur, sonst Wintertrace) und kann hier geändert werden.',

    // Datenaufbewahrung
    'retention_title'            => 'Datenaufbewahrung',
    'retention_description'      => 'Aufbewahrungsfrist und automatische Löschung konfigurieren.',
    'retention_years'            => 'Aufbewahrungsfrist (Jahre)',
    'retention_years_help'       => 'Einsatznachweise werden mindestens für diese Dauer aufbewahrt.',
    'retention_years_minimum_warning' => 'Die gesetzliche Mindestaufbewahrungsfrist beträgt :min Jahre.',
    'retention_auto_delete'      => 'Automatische Löschung',
    'retention_auto_delete_help' => 'Wenn aktiviert, werden Einsatzdaten nach Ablauf der Frist automatisch gelöscht.',
    'retention_legal_notice'     => 'Hinweis: Verkehrssicherungspflicht-Nachweise unterliegen gesetzlichen Aufbewahrungsfristen. Stellen Sie sicher, dass die gewählte Frist den rechtlichen Anforderungen genügt.',

    // Buttons
    'button_save'                => 'Einstellungen speichern',
];
