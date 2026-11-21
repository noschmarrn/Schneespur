<?php

/**
 * Helfer für update-smoke.sh — läuft IMMER in einem eigenen PHP-Prozeß.
 *
 *   php update-smoke-runner.php update   <basis> <zip> <version>
 *   php update-smoke-runner.php finalize <basis>
 *   php update-smoke-runner.php http     <basis> <pfad>
 *   php update-smoke-runner.php http-admin <basis> <pfad>   (als Admin angemeldet)
 *
 * "update" bootet die installierte (alte) Version und ruft ihren eigenen
 * Updater auf — genau so, wie der Update-Knopf es tut: alte Klassen im
 * Speicher, neue Dateien auf der Platte. Kennt der alte Updater schon die
 * zweistufige Form (stage/finalize, ab 1.2.2), endet "update" nach Stufe 1
 * und meldet das; die Shell startet dann "finalize" als frischen Prozeß.
 */

[$self, $mode, $base] = array_pad($argv, 3, null);

if (! in_array($mode, ['update', 'finalize', 'http', 'http-admin'], true) || ! is_dir((string) $base)) {
    fwrite(STDERR, "usage: update|finalize|http <basis> …\n");
    exit(2);
}

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';

if ($mode === 'http' || $mode === 'http-admin') {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    if ($mode === 'http-admin') {
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $admin = App\Models\User::where('email', 'smoke-admin@example.test')->first()
            ?? App\Models\User::forceCreate(['name' => 'Smoke', 'email' => 'smoke-admin@example.test', 'password' => bcrypt(bin2hex(random_bytes(8)))]);
        $admin->assignRole('admin');
        Illuminate\Support\Facades\Auth::guard('web')->setUser($admin->fresh());
    }
    $request = Illuminate\Http\Request::create($argv[3] ?? '/login', 'GET');
    $response = $kernel->handle($request);
    echo $response->getStatusCode(), "\n";
    if ($response->getStatusCode() >= 500 && isset($response->exception)) {
        fwrite(STDERR, '  '.get_class($response->exception).': '.substr($response->exception->getMessage(), 0, 200)."\n");
    }
    $kernel->terminate($request, $response);
    exit(0);
}

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$updater = app(App\Services\SchneespurUpdater::class);

try {
    if ($mode === 'finalize') {
        $updater->finalize();
        echo "FINALIZE OK\n";
        exit(0);
    }

    $zip = $argv[3];
    $version = $argv[4];
    $state = json_decode((string) @file_get_contents(storage_path('app/schneespur_update_state.json')), true) ?: [];
    $manifest = ['version' => $version, 'counter' => (int) ($state['last_counter'] ?? 0) + 1];

    if (method_exists($updater, 'stage')) {
        $updater->stage($zip, $manifest);
        echo "STAGED\n";
    } else {
        $updater->install($zip, $manifest);
        echo "INSTALLED\n";
    }
    exit(0);
} catch (Throwable $e) {
    echo 'FAILED: ', get_class($e), ': ', $e->getMessage(), "\n";
    exit(1);
}
