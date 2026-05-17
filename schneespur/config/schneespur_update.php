<?php

return [
    // Root-Pubkey EINKOMPILIERT — nicht aus .env, damit der Pin nicht aus Versehen
    // pro Environment auseinanderdriftet. Langlebig: Rotation = Code-Update.
    'root_pubkey_b64' => 'bbYkDrjwTapdcONvnhB3tfcwe0aA+lAcgnd0dLMlkmg=',

    'base_url'   => 'https://jenni.noschmarrn.dev',
    'slug'       => 'schneespur',

    'state_path'  => storage_path('app/schneespur_update_state.json'),
    'staging_dir' => storage_path('app/schneespur_staging'),
    'backup_dir'  => storage_path('app/schneespur_backups'),
];
