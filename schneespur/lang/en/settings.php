<?php

return [
    // Company & Location
    'company_title'              => 'Company & Location',
    'company_description'        => 'Manage company address, geocoding, and contact details.',
    'company_name'               => 'Company Name',
    'company_street'             => 'Street & Number',
    'company_zip'                => 'Postal Code',
    'company_city'               => 'City',
    'company_phone'              => 'Phone',
    'company_email'              => 'Email',
    'company_lat'                => 'Latitude',
    'company_lon'                => 'Longitude',
    'company_geocode_success'    => 'Address resolved successfully.',
    'company_geocode_fail'       => 'Address could not be resolved. Please enter coordinates manually.',
    'company_geocode_manual_hint' => 'Coordinates are determined automatically from the address. If resolution fails, you can enter latitude and longitude manually.',

    // Data Protection Officer
    'dpo_title'                  => 'Data Protection Officer',
    'dpo_help'                   => 'Contact details of the data protection officer or responsible person for data protection inquiries (GDPR Art. 37). This information is displayed in the GDPR notice for drivers.',
    'dpo_contact'                => 'Name / Contact Person',
    'dpo_contact_placeholder'    => 'e.g. John Smith or Privacy Corp.',
    'dpo_email'                  => 'Email',

    // Season period
    'season_title'               => 'Season Period',
    'season_from'                => 'Season Start',
    'season_to'                  => 'Season End',
    'season_help'                => 'Defines the active winter service period (e.g. November–March).',

    // Alert thresholds
    'alert_title'                => 'Alert Thresholds',
    'alert_overdue_hours'        => 'Overdue After (hours)',
    'alert_overdue_hours_help'   => 'Jobs open longer than this number of hours are flagged as overdue.',

    // Locale
    'locale_title'               => 'Language & Format',
    'locale_default'             => 'Default Language',
    'locale_help'                => 'Sets the display language and date format.',

    // Data retention
    'retention_title'            => 'Data Retention',
    'retention_description'      => 'Configure retention period and automatic deletion.',
    'retention_years'            => 'Retention Period (years)',
    'retention_years_help'       => 'Service records are kept for at least this duration.',
    'retention_years_minimum_warning' => 'The legal minimum retention period is :min years.',
    'retention_auto_delete'      => 'Automatic Deletion',
    'retention_auto_delete_help' => 'When enabled, service records are automatically deleted after the retention period expires.',
    'retention_legal_notice'     => 'Note: Traffic safety obligation records are subject to statutory retention periods. Ensure the chosen period meets legal requirements.',

    // Buttons
    'button_save'                => 'Save Settings',
];
