<?php

return [
    'server_url'       => 'https://jenni.noschmarrn.dev',
    'collection_slug'  => 'schneespur-module',
    'catalog_endpoint' => '/api/modules/{slug}',
    'timeout'          => 10,
    'download_timeout' => 120,
    'state_file_path'  => storage_path('app/schneespur_modules_state.json'),
    'modules_path'     => base_path('modules'),
];
