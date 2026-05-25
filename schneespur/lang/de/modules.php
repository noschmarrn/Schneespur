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

    'signature_verified' => 'Signiert',
    'signature_unsigned' => 'Unsigniert',
    'signature_failed_badge' => 'Signatur ungültig',
    'signature_failed' => 'Signatur ungültig (":slug"): :reason',
    'signature_unknown' => 'Unbekannt',
    'signature_verified_tooltip' => 'Dieses Modul wurde kryptographisch verifiziert.',
    'signature_unsigned_tooltip' => 'Dieses Modul wurde nicht kryptographisch verifiziert.',
    'unsigned_warning' => 'Modul ":slug" wurde installiert, ist aber nicht signiert.',
    'trust_refresh_failed' => 'Trust-Liste konnte nicht aktualisiert werden: :error',

    'trust_official' => 'Offiziell',
    'trust_verified' => 'Verifiziert',
    'trust_community' => 'Community',
    'trust_unknown' => 'Unbekannt',
    'trust_official_tooltip' => 'Dieses Modul stammt vom Schneespur-Team.',
    'trust_verified_tooltip' => 'Dieses Modul wurde vom Schneespur-Team geprüft.',
    'trust_community_tooltip' => 'Dieses Modul stammt von einem Drittanbieter.',
    'trust_unknown_tooltip' => 'Trust-Level für dieses Modul ist nicht bekannt.',
    'trust_filter_label' => 'Trust-Level',
    'trust_filter_all' => 'Alle',
    'trust_community_install_warning' => 'Dieses Modul stammt von einem Drittanbieter und wurde nicht vom Schneespur-Team geprüft. Trotzdem installieren?',

    'api_tokens_title' => 'API-Tokens',
    'api_tokens_description' => 'API-Tokens für das Modul ":name" verwalten.',
    'no_tokens' => 'Noch keine API-Tokens erstellt.',

    'token_col_name' => 'Name',
    'token_col_created' => 'Erstellt',
    'token_col_last_used' => 'Zuletzt verwendet',
    'token_col_expires' => 'Läuft ab',

    'token_expired' => 'Abgelaufen',
    'token_never_used' => 'Nie verwendet',
    'token_no_expiry' => 'Kein Ablaufdatum',

    'token_create_title' => 'Token erstellen',
    'token_field_name' => 'Bezeichnung',
    'token_field_name_placeholder' => 'z.B. Wetterstation-Integration',
    'token_field_expires' => 'Ablaufdatum (optional)',
    'token_field_expires_hint' => 'Leer lassen für unbefristeten Token.',

    'token_btn_create' => 'Neuer Token',
    'token_btn_generate' => 'Token erstellen',
    'token_btn_revoke' => 'Widerrufen',
    'token_btn_confirm_revoke' => 'Token widerrufen',

    'token_show_once_title' => 'Neuer API-Token',
    'token_show_once_warning' => 'Dieser Token wird nur einmal angezeigt. Bitte jetzt kopieren und sicher aufbewahren.',
    'token_copy' => 'Kopieren',
    'token_copied' => 'Kopiert!',

    'token_confirm_revoke_title' => 'Token widerrufen',
    'token_confirm_revoke' => 'Diesen Token wirklich widerrufen? Alle Integrationen, die diesen Token verwenden, verlieren sofort den Zugang.',

    'token_created' => 'Token ":name" wurde erstellt.',
    'token_revoked' => 'Token ":name" wurde widerrufen.',

    'logs_title' => 'Modul-Logs',
    'logs_description' => 'Letzte Events und Log-Einträge für das Modul ":name".',
    'log_col_time' => 'Zeitpunkt',
    'log_col_level' => 'Level',
    'log_col_message' => 'Nachricht',
    'log_col_context' => 'Kontext',
    'log_level_info' => 'Info',
    'log_level_warning' => 'Warnung',
    'log_level_error' => 'Fehler',
    'no_logs' => 'Keine Log-Einträge vorhanden.',
    'btn_logs' => 'Logs',
    'log_filter_label' => 'Level-Filter',
    'log_filter_all' => 'Alle',
];
