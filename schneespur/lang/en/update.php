<?php

return [
    // Settings index card
    'settings_title'             => 'Auto-Update',
    'settings_description'       => 'Automatic updates and version management.',

    // Settings page
    'page_title'                 => 'Auto-Update',
    'current_version'            => 'Current Version',
    'auto_check_label'           => 'Automatic Update Check',
    'auto_check_help'            => 'When enabled, :app_name checks daily for new versions.',
    'auto_check_enabled'         => 'Enabled (daily at 04:17)',
    'auto_check_disabled'        => 'Disabled',

    // Check now
    'check_now'                  => 'Check Now',
    'checking'                   => 'Checking…',
    'check_result_up_to_date'    => ':app_name is up to date.',
    'check_result_update'        => 'Update available: Version :version',
    'check_result_error'         => 'Update check failed: :error',
    'check_result_no_release'    => 'No release available on the update server yet.',

    // Update details
    'changelog'                  => 'Changelog',
    'released_at'                => 'Released',
    'download_size'              => 'Download Size',

    // Install
    'install_button'             => 'Install Update',
    'installing'                 => 'Installing update…',
    'install_success'            => 'Update to version :version installed successfully.',
    'install_failed'             => 'Update failed: :error',

    // Backup
    'backup_title'               => 'Backup Recommendation',
    'backup_warning'             => 'It is strongly recommended to create a backup of your database and files before updating.',
    'backup_db_info'             => 'Database: :host / :database',
    'backup_instructions'        => 'Create a backup via phpMyAdmin (SQL export) and download your files via FTP.',
    'backup_confirm'             => 'I have created a backup',

    // Trust info
    'trust_title'                => 'Trust Status',
    'trust_version'              => 'Trust Version',
    'trust_expires'              => 'Valid Until',
    'trust_keys'                 => 'Active Signing Keys',
    'trust_not_loaded'           => 'Not loaded yet',

    // Sodium missing
    'sodium_missing'             => 'The PHP extension "sodium" is not loaded. Auto-updates cannot be verified.',

    // Dashboard widget
    'dashboard_title'            => 'Update Status',
    'dashboard_up_to_date'       => 'Up to date',
    'dashboard_update_available' => 'Update available',
    'dashboard_never_checked'    => 'Not checked yet',
    'dashboard_last_checked'     => 'Last checked',
    'dashboard_version'          => 'Version',

    // Artisan output
    'artisan_up_to_date'         => 'Already on latest version.',
    'artisan_update_available'   => 'Update available: :version (counter :counter, signed :signed_at)',
    'artisan_apply_hint'         => '--apply to download + verify ZIP.',
    'artisan_zip_verified'       => 'ZIP verified: :path',
    'artisan_check_failed'       => 'Update check failed: :error',
    'artisan_zip_failed'         => 'ZIP verification failed: :error',

    // Preflight
    'preflight_title'            => 'Prerequisites',
    'preflight_ok'               => 'All prerequisites met.',
    'preflight_fail'             => 'Not all prerequisites met:',
    'preflight_sodium'           => 'PHP extension sodium',
    'preflight_zip'              => 'PHP extension zip',
    'preflight_writable'         => 'Directory writable',
    'preflight_disk_space'       => 'Sufficient disk space',

    // Recovery
    'recovery_no_info'           => 'No recovery information available.',
    'recovery_still_maintenance' => 'Warning: Application is still in maintenance mode.',
    'recovery_confirm_up'        => 'Disable maintenance mode?',
    'recovery_maintenance_disabled' => 'Maintenance mode disabled.',
    'recovery_found'             => 'Failed update detected:',
    'recovery_steps_title'       => 'Recommended steps:',
    'recovery_confirm_restore'   => 'Restore backup?',
    'recovery_restore_success'   => 'Backup restored successfully.',
    'recovery_restore_failed'    => 'Backup restoration failed.',
    'recovery_no_backup'         => 'Backup directory not found — manual recovery required.',
    'recovery_cleared'           => 'Recovery information cleared.',
];
