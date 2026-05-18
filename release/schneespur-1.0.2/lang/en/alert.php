<?php

return [
    'page_title'           => 'Alerts',
    'dashboard_heading'    => 'Data Quality & Alerts',

    // Filter labels
    'filter_type'          => 'Type',
    'filter_type_all'      => 'All Types',
    'filter_date_from'     => 'From',
    'filter_date_to'       => 'To',
    'filter_status'        => 'Status',
    'filter_status_open'   => 'Open',
    'filter_status_resolved' => 'Resolved',
    'filter_btn'           => 'Filter',
    'filter_reset'         => 'Reset',

    // Alert type names
    'type_missing_gps'     => 'Missing GPS',
    'type_missing_weather' => 'Missing Weather',
    'type_overdue'         => 'Overdue',

    // Summary badges
    'badge_total'          => 'Total',

    // Table headers
    'col_job'              => 'Job',
    'col_customer'         => 'Customer',
    'col_driver'           => 'Driver',
    'col_type'             => 'Type',
    'col_date'             => 'Date',
    'col_actions'          => 'Action',
    'col_resolved_at'      => 'Resolved at',
    'col_resolved_by'      => 'Resolved by',
    'col_note'             => 'Note',

    // Action buttons
    'btn_resolve'          => 'Resolve',
    'btn_resolve_submit'   => 'Mark as resolved',
    'btn_resolve_cancel'   => 'Cancel',
    'btn_bulk_resolve'     => 'Resolve all',
    'btn_view_job'         => 'View Job',

    // Resolve form
    'resolve_note_label'   => 'Note (optional)',
    'resolve_note_placeholder' => 'Why is this alert being resolved?',

    // Confirm dialogs
    'bulk_confirm'         => 'Mark all open alerts of this type as resolved?',

    // Empty states
    'empty_heading'        => 'No Alerts',
    'empty_body'           => 'There are currently no open alerts.',
    'empty_filtered'       => 'No alerts found for the selected filters.',
    'select_type_heading'  => 'Select a Type',
    'select_type_body'     => 'Please select an alert type from the filters or click one of the summaries above.',

    // Dashboard cards
    'card_all_clear'       => 'All Clear',
    'card_all_clear_body'  => 'No open alerts at this time.',
    'card_recent_jobs'     => 'Recent affected jobs:',
    'card_no_jobs'         => 'No affected jobs.',
    'card_view_all'        => 'View all',

    // Misc
    'no_note'              => 'No note',
    'date_format'          => 'm/d/Y',
];
