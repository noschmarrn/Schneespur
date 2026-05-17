<?php

namespace App\Services\Installer;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;

class MigrationRunner
{
    public function run(): array
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            return [
                'success' => $exitCode === 0,
                'output' => $output,
                'error' => $exitCode !== 0 ? $output : null,
            ];
        } catch (QueryException $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }
}
