<?php

return [
    // Step titles
    'title_step_1'                   => 'Welcome to :app_name',
    'title_step_2'                   => 'System Requirements',
    'title_step_3'                   => 'Connect Database',
    'title_step_4'                   => 'Database Migrations',
    'title_step_5'                   => 'App Configuration',
    'title_step_6'                   => 'Storage & Caches',
    'title_step_7'                   => 'Create Admin Account',
    'title_step_8'                   => 'Optional: Send Test Email',
    'title_step_9'                   => 'Set Up Cron Job',
    'title_done'                     => 'Installation Complete',

    // Stepper
    'stepper_step_1'                 => 'Welcome',
    'stepper_step_2'                 => 'Requirements',
    'stepper_step_3'                 => 'Database',
    'stepper_step_4'                 => 'Migrations',
    'stepper_step_5'                 => 'Configuration',
    'stepper_step_6'                 => 'Storage & Caches',
    'stepper_step_7'                 => 'Admin Account',
    'stepper_step_8'                 => 'Test Email',
    'stepper_step_9'                 => 'Cron Job',
    'stepper_mobile'                 => 'Step :current of 9: :title',

    // Buttons (shared)
    'btn_finalize'                   => 'Complete Installation',
    'btn_retry_migration'            => 'Retry',
    'btn_copy_error'                 => 'Copy error message',
    'btn_back'                       => 'Back',
    'btn_continue'                   => 'Continue',
    'btn_skip'                       => 'Skip',

    // Step 1: Welcome
    'welcome_intro'                  => 'Welcome to the :app_name installation wizard.',
    'welcome_description'            => 'In just a few steps, you will set up your winter service documentation. The wizard will guide you through the database connection, system check, configuration, and admin account setup.',
    'welcome_steps_heading'          => 'The installation includes the following steps:',
    'welcome_system_info'            => 'System Information',
    'welcome_server'                 => 'Server',
    'welcome_start_btn'              => 'Start Installation',
    'welcome_key_generated'          => 'Application key was generated successfully.',

    // Step 2: Database
    'db_host_label'                  => 'Database Host',
    'db_port_label'                  => 'Port',
    'db_name_label'                  => 'Database Name',
    'db_user_label'                  => 'Username',
    'db_pass_label'                  => 'Password',
    'db_submit_btn'                  => 'Test connection & save',
    'db_test_success'                => 'Database connection established successfully.',

    // Step 3: Preflight
    'preflight_heading'              => ':app_name is checking whether all system requirements are met.',
    'preflight_all_passed'           => 'All requirements are met. You may continue.',
    'preflight_has_warnings'         => 'There are warnings, but the installation can proceed.',
    'preflight_has_failures'         => 'Critical requirements are not met. Please resolve the highlighted issues before continuing.',
    'preflight_continue_btn'         => 'Continue',

    // Step 4: Migrations
    'migration_heading'              => 'The database tables will now be created. This may take a few seconds.',
    'migration_run_btn'              => 'Run Migrations',
    'migration_success'              => 'All database tables were created successfully.',
    'migration_error_copied'         => 'Copied!',

    // Step 5: Config
    'config_url_label'               => 'App URL',
    'config_url_help'                => 'The full URL where :app_name is accessible (e.g. https://schneespur.example.com)',
    'config_tz_label'                => 'Timezone',
    'config_tz_detected'             => 'Pre-selected from your browser when possible.',
    'config_locale_label'            => 'Language',
    'config_brand_hint'              => 'The app will be installed as ":brand".',
    'config_submit_btn'              => 'Save Configuration',

    // Step 6: Storage
    'storage_heading'                => 'Storage link and caches will now be set up.',
    'storage_run_btn'                => 'Run',
    'storage_link_label'             => 'Storage Link',
    'storage_config_cache_label'     => 'Cache configuration',
    'storage_view_cache_label'       => 'Cache views',
    'storage_link_success'           => 'Storage link was created successfully.',
    'storage_config_cache_success'   => 'Configuration cached successfully.',
    'storage_view_cache_success'     => 'Views cached successfully.',
    'storage_link_warning'           => 'The storage link could not be created automatically. :app_name uses an alternative delivery method — photos and uploads will still work.',

    // Step 7: Admin
    'admin_name_label'               => 'Name',
    'admin_email_label'              => 'Email Address',
    'admin_pass_label'               => 'Password',
    'admin_pass_confirm_label'       => 'Confirm Password',
    'admin_submit_btn'               => 'Create Admin Account',

    // Step 8: Mail
    'mail_heading'                   => 'Configure SMTP delivery for email notifications. You can also skip this step.',
    'mail_host_label'                => 'SMTP Host',
    'mail_port_label'                => 'Port',
    'mail_encryption_label'          => 'Encryption',
    'mail_encryption_none'           => 'None',
    'mail_scheme_starttls'           => 'STARTTLS — Port 587 (standard)',
    'mail_scheme_ssl'                => 'SSL — Port 465 (older servers)',
    'mail_scheme_none'               => 'No encryption (Port 25 — local only)',
    'mail_user_label'                => 'Username',
    'mail_pass_label'                => 'Password',
    'mail_from_label'                => 'From Address',
    'mail_from_name_label'           => 'From Name',
    'mail_test_recipient_label'      => 'Test Recipient',
    'mail_submit_btn'                => 'Send Test Email',
    'mail_skip_btn'                  => 'Skip this step',
    'mail_test_body'                 => 'This is a test email from :brand.',
    'mail_test_subject'              => 'Test Email',
    'mail_test_success'              => 'Test email was sent successfully.',
    'mail_test_error'                => 'Error sending the test email.',

    // Step 9: Cron
    'cron_heading'                   => ':app_name needs to run background tasks regularly — fetching weather data, sending notifications, and deleting old data. Set up a cron job with your hosting provider.',
    'cron_line_label'                => 'Enter this line in your host\'s cron job management:',
    'cron_instructions_heading'      => 'Instructions',
    'cron_step_1'                    => 'Log in to your hosting control panel (Plesk, cPanel, Confixx, etc.).',
    'cron_step_2'                    => 'Find the "Scheduled Tasks" or "Cron Jobs" section.',
    'cron_step_3'                    => 'Create a new cron job with the interval "Every minute" (or the lowest available interval) and the command above.',
    'cron_test_btn'                  => 'Run test now',
    'cron_test_success'              => 'Background tasks have been executed successfully.',
    'cron_active'                    => 'Cron job is active and working.',
    'cron_fallback_note'             => ':app_name works without a cron job too — weather data will be fetched directly when saving a job (slower). With a cron job, the app is faster and more reliable.',
    'btn_copy'                       => 'Copy',
    'btn_copied'                     => 'Copied!',

    // Done
    'done_description'               => ':app_name has been installed successfully. You can now log in with your admin account.',
    'done_login_btn'                 => 'Go to Login',
    'done_summary_url'               => 'App URL',
    'done_summary_admin'             => 'Admin Email',
    'done_summary_mail'              => 'Email configured',
    'done_mail_yes'                  => 'Yes',
    'done_mail_no'                   => 'No (can be set up later)',

    // .env fallback
    'env_fallback_instructions'      => 'Copy the content below and upload it as a `.env` file via FTP to the root directory of your :app_name installation.',
    'env_fallback_copy_btn'          => 'Copy to clipboard',
    'env_fallback_copied'            => 'Copied!',
    'env_fallback_recheck'           => 'Recheck',

    // Errors
    'error_db_connection'            => 'Database connection failed. Please check the host, database name, username, and password.',
    'error_migration_main'           => 'The database migration could not be executed. Please check the error message below and try again.',
    'error_migration_hint'           => ':app_name can retry the migration as often as needed — no data will be lost.',
    'error_env_write'                => 'The configuration file could not be written. Copy the content below into your `.env` file and upload it via FTP.',

    // Flash messages
    'flash_complete'                 => 'Installation complete. You can now log in.',
    'flash_migration_retry_success'  => 'The migration was completed successfully.',
    'flash_test_mail'                => 'Test email has been sent to :email. Please check your inbox.',

    // Guard
    'already_installed'              => ':app_name is already installed.',
];
