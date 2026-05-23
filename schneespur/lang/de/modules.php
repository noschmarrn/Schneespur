<?php

return [
    'page_title' => 'Module',
    'no_modules' => 'Keine Module verfügbar.',
    'no_installed' => 'Keine Module installiert.',
    'no_available' => 'Keine weiteren Module im Katalog verfügbar.',
    'catalog_error_notice' => 'Katalog konnte nicht geladen werden',

    'section_installed' => 'Installierte Module',
    'section_available' => 'Verfügbare Module',

    'status_enabled' => 'Aktiviert',
    'status_disabled' => 'Deaktiviert',
    'status_not_installed' => 'Nicht installiert',
    'update_available' => 'Update auf v:version verfügbar',

    'orphan_badge' => 'Verwaist',
    'orphan_tooltip' => 'Dieses Modul ist installiert, aber nicht mehr im Katalog vorhanden.',

    'btn_install' => 'Installieren',
    'btn_update' => 'Aktualisieren',
    'btn_enable' => 'Aktivieren',
    'btn_disable' => 'Deaktivieren',
    'btn_remove' => 'Entfernen',
    'btn_cancel' => 'Abbrechen',
    'btn_confirm_remove' => 'Endgültig entfernen',
    'confirm_remove_title' => 'Modul entfernen',
    'confirm_remove' => 'Modul wirklich entfernen? Alle Modul-Dateien werden gelöscht.',

    'settings_card_title' => 'Module',
    'settings_card_description' => 'Erweiterungen installieren, aktivieren und verwalten.',

    'installed' => 'Modul ":slug" wurde erfolgreich installiert.',
    'updated' => 'Modul ":slug" wurde erfolgreich aktualisiert.',
    'enabled' => 'Modul ":slug" wurde aktiviert.',
    'disabled' => 'Modul ":slug" wurde deaktiviert.',
    'removed' => 'Modul ":slug" wurde entfernt.',

    'catalog_fetch_failed' => 'Katalog konnte nicht abgerufen werden: :error',
    'catalog_unavailable' => 'Katalog momentan nicht verfügbar.',
    'not_found_in_catalog' => 'Modul ":slug" nicht im Katalog gefunden.',
    'not_installed' => 'Modul ":slug" ist nicht installiert.',
    'install_failed' => 'Installation von ":slug" fehlgeschlagen: :error',
    'update_failed' => 'Aktualisierung von ":slug" fehlgeschlagen: :error',
    'directory_exists' => 'Modulverzeichnis existiert bereits.',
    'extraction_failed' => 'ZIP-Entpacken fehlgeschlagen.',

    'permission_tooltip' => 'Dieses Modul benötigt diese Berechtigung.',

    'migration_failed' => 'Datenbank-Migration für ":slug" fehlgeschlagen: :error',
    'migration_rollback_warning' => 'Migrations-Rollback für ":slug" fehlgeschlagen: :error',

    'dependency_missing' => 'Modul ":slug" benötigt ":dependency" (:constraint), aber es ist nicht aktiv.',
    'dependency_version' => 'Modul ":slug" benötigt ":dependency" :constraint, aber Version :actual ist installiert.',
    'dependency_conflict' => 'Modul ":slug" steht in Konflikt mit ":conflict", das gerade aktiv ist.',
    'has_dependants' => '":slug" kann nicht deaktiviert werden — folgende Module hängen davon ab: :dependants.',
    'circular_dependency' => 'Modul ":slug" würde eine zirkuläre Abhängigkeit erzeugen: :cycle.',

    'cli_has_dependants' => '":slug" kann nicht entfernt werden — folgende aktive Module hängen davon ab: :dependants. Verwende --force zum Überschreiben.',
    'cli_force_dependants_warning' => 'Warnung: ":slug" wird trotz aktiver Abhängigkeiten entfernt: :dependants.',
];
