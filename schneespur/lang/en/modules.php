<?php

return [
    'page_title' => 'Modules',
    'no_modules' => 'No modules available.',
    'no_installed' => 'No modules installed.',
    'no_available' => 'No additional modules available in catalog.',
    'catalog_error_notice' => 'Failed to load catalog',

    'section_installed' => 'Installed Modules',
    'section_available' => 'Available Modules',

    'status_enabled' => 'Enabled',
    'status_disabled' => 'Disabled',
    'status_not_installed' => 'Not installed',
    'update_available' => 'Update to v:version available',

    'orphan_badge' => 'Orphan',
    'orphan_tooltip' => 'This module is installed but no longer found in the catalog.',

    'btn_install' => 'Install',
    'btn_update' => 'Update',
    'btn_enable' => 'Enable',
    'btn_disable' => 'Disable',
    'btn_remove' => 'Remove',
    'btn_cancel' => 'Cancel',
    'btn_confirm_remove' => 'Remove permanently',
    'confirm_remove_title' => 'Remove module',
    'confirm_remove' => 'Really remove this module? All module files will be deleted.',

    'settings_card_title' => 'Modules',
    'settings_card_description' => 'Install, enable, and manage extensions.',

    'installed' => 'Module ":slug" installed successfully.',
    'updated' => 'Module ":slug" updated successfully.',
    'enabled' => 'Module ":slug" enabled.',
    'disabled' => 'Module ":slug" disabled.',
    'removed' => 'Module ":slug" removed.',

    'catalog_fetch_failed' => 'Failed to fetch catalog: :error',
    'catalog_unavailable' => 'Catalog currently unavailable.',
    'not_found_in_catalog' => 'Module ":slug" not found in catalog.',
    'not_installed' => 'Module ":slug" is not installed.',
    'install_failed' => 'Installation of ":slug" failed: :error',
    'update_failed' => 'Update of ":slug" failed: :error',
    'directory_exists' => 'Module directory already exists.',
    'extraction_failed' => 'ZIP extraction failed.',

    'permission_tooltip' => 'This module requires this permission.',

    'migration_failed' => 'Database migration for ":slug" failed: :error',
    'migration_rollback_warning' => 'Migration rollback for ":slug" failed: :error',

    'dependency_missing' => 'Module ":slug" requires ":dependency" (:constraint), but it is not active.',
    'dependency_version' => 'Module ":slug" requires ":dependency" :constraint, but version :actual is installed.',
    'dependency_conflict' => 'Module ":slug" conflicts with ":conflict" which is currently active.',
    'has_dependants' => 'Cannot disable ":slug" — the following modules depend on it: :dependants.',
];
