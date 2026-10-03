<?php

return [
    // Company & Location
    'company_title'              => 'Company & Location',
    'company_description'        => 'Manage company address, geocoding, and contact details.',
    'company_name'               => 'Company Name',
    'company_street'             => 'Street & Number',
    'company_zip'                => 'Postal Code',
    'company_city'               => 'City',
    'company_state'              => 'State / Province',
    'company_phone'              => 'Phone',
    'company_email'              => 'Email',
    'company_lat'                => 'Latitude',
    'company_lon'                => 'Longitude',
    'company_geocode_success'    => 'Address resolved successfully.',
    'company_geocode_fail'       => 'Address could not be resolved. Please check street, postal code and city.',
    'company_geocode_manual_hint' => 'Coordinates are determined automatically from the address.',

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
    'billing_title'              => 'Billing',
    'billing_increment'          => 'Billing increment for hourly prices',
    'billing_increment_exact'    => 'To the minute',
    'billing_increment_step'     => 'Per started :minutes minutes',
    'report_show_amounts'        => 'Show amounts on the collective report',
    'report_show_amounts_help'   => 'The collective report goes to the customer. When switched on, it carries the total of jobs and flat rates for the period. The internal report and the CSV export always show amounts.',
    'billing_increment_help'     => 'Applies to sites priced per hour. The increment is stored with the amount on each job; changing it affects future jobs only.',
    'alert_title'                => 'Alert Thresholds',
    'alert_overdue_hours'        => 'Overdue After (hours)',
    'alert_overdue_hours_help'   => 'Jobs open longer than this number of hours are flagged as overdue.',

    // Locale
    'locale_title'               => 'Language & Format',
    'locale_default'             => 'Default Language',
    'locale_help'                => 'Sets the display language and date format.',
    'timezone_label'             => 'Time zone',
    'timezone_help'              => 'Decides the time in which jobs are entered and shown. Storage stays in UTC either way.',
    'timezone_invalid'           => 'The selected time zone is not a valid identifier.',
    'brand_label'                => 'Product name',
    'brand_help'                 => 'The name this software appears under in the interface, the PWA and outgoing email. Derived from the install language (German → Schneespur, otherwise Wintertrace) and changeable here.',

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
