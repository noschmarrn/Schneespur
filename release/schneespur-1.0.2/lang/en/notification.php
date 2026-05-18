<?php

return [
    'job_completed_subject' => 'Service Report: :customer on :date',
    'job_completed_updated_subject' => 'Updated Service Report: :customer on :date',
    'greeting' => 'Dear :name,',
    'job_completed_body' => 'a service operation (:type) was performed for you on :date. Time: :time_start – :time_end. Driver: :driver.',
    'weather_summary' => 'Weather: :temperature°C, :precipitation mm precipitation',
    'weather_unavailable' => 'Weather data was not available at the time of the service operation.',
    'weather_update_note' => 'This is an updated service report with weather data now available.',
    'pdf_attached' => 'Please find the complete service report attached as a PDF.',
    'pdf_too_large' => 'The service report is too large for an email attachment. You can download it from the system.',
    'regards' => 'Kind regards',
    'date_format' => 'm/d/Y',

    'customer_report_subject' => 'Combined Service Report: :customer (:from – :to)',
    'customer_report_greeting' => 'Dear :name,',
    'customer_report_body' => 'please find attached the combined service report for the period :from to :to.',
    'customer_report_object_body' => 'please find attached the combined service report for site :object covering the period :from to :to.',
    'customer_report_pdf_attached' => 'The complete combined service report is attached as a PDF.',
    'customer_report_pdf_too_large' => 'The combined service report is too large for an email attachment. Please download it from the system.',

    'customer_report_email_sent' => 'The combined service report is being sent via email.',
    'customer_report_email_duplicate' => 'This report was already sent within the last 5 minutes. Please wait a moment.',
    'customer_report_email_no_email' => 'No email address is configured for this customer.',

    // Notification Log
    'page_notification_log' => 'Notification Log',
    'filter_status' => 'Status',
    'filter_type' => 'Type',
    'filter_date_from' => 'Date from',
    'filter_date_to' => 'Date to',
    'filter_btn' => 'Filter',
    'filter_reset' => 'Reset',
    'filter_all' => 'All',
    'col_date' => 'Date',
    'col_customer' => 'Customer',
    'col_recipient' => 'Recipient',
    'col_type' => 'Type',
    'col_status' => 'Status',
    'col_error' => 'Error',
    'status_sent' => 'Sent',
    'status_failed' => 'Failed',
    'status_skipped' => 'Skipped',
    'type_job_completed' => 'Job completed',
    'type_customer_report' => 'Customer report',
    'empty_log' => 'No notifications found.',

    // Settings cards
    'settings_card_email' => 'Email Settings',
    'settings_card_email_desc' => 'Configure SMTP server and sender address.',
    'settings_card_log' => 'Notification Log',
    'settings_card_log_desc' => 'View all sent and failed emails.',

    // Email settings
    'page_email_settings' => 'Email Settings',
    'smtp_section' => 'SMTP Configuration',
    'sender_section' => 'Sender',
    'test_email_section' => 'Test Email',

    // Field labels
    'field_mail_mailer' => 'Mailer',
    'field_mail_host' => 'SMTP Host',
    'field_mail_port' => 'SMTP Port',
    'field_mail_scheme' => 'Encryption',
    'field_mail_username' => 'Username',
    'field_mail_password' => 'Password',
    'field_mail_from_address' => 'From Address',
    'field_mail_from_name' => 'From Name',
    'password_placeholder_help' => 'Leave empty to keep existing password.',
    'hint_mail_host' => 'e.g. smtp.strato.de, smtp.ionos.de, smtp.gmail.com',
    'hint_mail_port' => '587 (STARTTLS, recommended) or 465 (SSL)',

    // Scheme options
    'scheme_none' => 'None',
    'scheme_auto' => 'Automatic (recommended)',
    'scheme_starttls' => 'STARTTLS — Port 587',
    'scheme_ssl' => 'SSL — Port 465',

    // Test email
    'test_email_btn' => 'Send Test Email',
    'test_email_subject' => ':app_name Test Email',
    'test_email_body' => 'This is a test email from :app_name. Your SMTP configuration is working correctly.',
    'test_email_help' => 'A test email will be sent to :email.',
    'test_email_help_configurable' => 'Send a test email to verify your SMTP configuration.',
    'test_email_recipient' => 'Recipient Address',
    'test_email_success' => 'Test email was sent successfully.',
    'test_email_sent_to' => 'Test email submitted to SMTP server (recipient: :email). Please check inbox — you will receive a bounce notification if delivery fails.',
    'test_email_failed' => 'Test email failed',

    // Save / env messages
    'email_saved' => 'Email settings saved.',
    'env_not_writable' => 'The .env file is not writable.',
    'env_copy_instructions' => 'Copy the configuration below and paste it manually into your .env file.',
    'env_copy_btn' => 'Copy to clipboard',
    'env_copied' => 'Copied!',
    'env_recheck' => 'Recheck',

    // Portal credentials email
    'portal_credentials_subject'          => 'Your Portal Access',
    'portal_credentials_reset_subject'    => 'Your New Portal Password',
    'portal_credentials_body'             => 'a portal access has been set up for you. You can log in with the following credentials:',
    'portal_credentials_reset_body'       => 'your portal password has been reset. Your new credentials are:',
    'portal_credentials_email_label'      => 'Email:',
    'portal_credentials_password_label'   => 'Password:',
    'portal_credentials_change_hint'      => 'Please change your password after first login.',
    'type_portal_credentials'             => 'Portal credentials',
];
