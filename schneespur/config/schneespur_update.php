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

    // Zwischen den zwei Update-Stufen (siehe SchneespurUpdater::stage/finalize).
    'pending_path'  => storage_path('app/schneespur_update_pending.json'),
    'recovery_path' => storage_path('app/schneespur_update_recovery.json'),

    // Zielverzeichnis der Dateikopie. Leer = base_path(); nur Tests setzen es.
    'install_dir' => null,

    // Stufe 2 baut config-, route- und view-Cache. Nur Tests schalten das ab.
    'build_caches' => true,
];
