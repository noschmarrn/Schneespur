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
    'circular_dependency' => 'Module ":slug" would create a circular dependency: :cycle.',

    'cli_has_dependants' => 'Cannot remove ":slug" — the following active modules depend on it: :dependants. Use --force to override.',
    'cli_force_dependants_warning' => 'Warning: removing ":slug" despite active dependants: :dependants.',

    'signature_verified' => 'Signed',
    'signature_unsigned' => 'Unsigned',
    'signature_failed_badge' => 'Invalid signature',
    'signature_failed' => 'Invalid signature (":slug"): :reason',
    'signature_unknown' => 'Unknown',
    'signature_verified_tooltip' => 'This module has been cryptographically verified.',
    'signature_unsigned_tooltip' => 'This module has not been cryptographically verified.',
    'unsigned_warning' => 'Module ":slug" was installed but is not signed.',
    'trust_refresh_failed' => 'Failed to refresh trust list: :error',

    'trust_official' => 'Official',
    'trust_verified' => 'Verified',
    'trust_community' => 'Community',
    'trust_unknown' => 'Unknown',
    'trust_official_tooltip' => 'This module is maintained by the Schneespur team.',
    'trust_verified_tooltip' => 'This module has been reviewed by the Schneespur team.',
    'trust_community_tooltip' => 'This module is from a third-party developer.',
    'trust_unknown_tooltip' => 'Trust level for this module is unknown.',
    'trust_filter_label' => 'Trust Level',
    'trust_filter_all' => 'All',
    'trust_community_install_warning' => 'This module is from a third-party developer and has not been reviewed by the Schneespur team. Install anyway?',
];
