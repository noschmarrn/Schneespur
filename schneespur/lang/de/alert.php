<?php

return [
    'page_title'           => 'Warnungen',
    'dashboard_heading'    => 'Datenqualität & Warnungen',

    // Filter labels
    'filter_type'          => 'Typ',
    'filter_type_all'      => 'Alle Typen',
    'filter_date_from'     => 'Von',
    'filter_date_to'       => 'Bis',
    'filter_status'        => 'Status',
    'filter_status_open'   => 'Offen',
    'filter_status_resolved' => 'Erledigt',
    'filter_btn'           => 'Filtern',
    'filter_reset'         => 'Zurücksetzen',

    // Alert type names
    'type_missing_gps'     => 'GPS fehlt',
    'type_missing_weather' => 'Wetter fehlt',
    'type_overdue'         => 'Überfällig',

    // Summary badges
    'badge_total'          => 'Gesamt',

    // Table headers
    'col_job'              => 'Einsatz',
    'col_customer'         => 'Kunde',
    'col_driver'           => 'Fahrer',
    'col_type'             => 'Typ',
    'col_date'             => 'Datum',
    'col_actions'          => 'Aktion',
    'col_resolved_at'      => 'Erledigt am',
    'col_resolved_by'      => 'Erledigt von',
    'col_note'             => 'Notiz',

    // Action buttons
    'btn_resolve'          => 'Erledigen',
    'btn_resolve_submit'   => 'Als erledigt markieren',
    'btn_resolve_cancel'   => 'Abbrechen',
    'btn_bulk_resolve'     => 'Alle erledigen',
    'btn_view_job'         => 'Einsatz ansehen',

    // Resolve form
    'resolve_note_label'   => 'Notiz (optional)',
    'resolve_note_placeholder' => 'Warum wird dieser Alert erledigt?',

    // Confirm dialogs
    'bulk_confirm'         => 'Alle offenen Alerts dieses Typs als erledigt markieren?',

    // Empty states
    'empty_heading'        => 'Keine Warnungen',
    'empty_body'           => 'Es gibt aktuell keine offenen Warnungen.',
    'empty_filtered'       => 'Keine Warnungen für die gewählten Filter gefunden.',
    'select_type_heading'  => 'Typ auswählen',
    'select_type_body'     => 'Bitte wählen Sie einen Warnungstyp aus den Filtern oder klicken Sie auf eine der Zusammenfassungen oben.',

    // Dashboard cards
    'card_all_clear'       => 'Alles in Ordnung',
    'card_all_clear_body'  => 'Keine offenen Warnungen vorhanden.',
    'card_recent_jobs'     => 'Letzte betroffene Einsätze:',
    'card_no_jobs'         => 'Keine betroffenen Einsätze.',
    'card_view_all'        => 'Alle anzeigen',

    // Misc
    'no_note'              => 'Keine Notiz',
    'date_format'          => 'd.m.Y',
];
