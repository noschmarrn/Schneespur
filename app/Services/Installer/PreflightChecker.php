<?php

namespace App\Services\Installer;

class PreflightChecker
{
    public function check(): array
    {
        return array_merge(
            $this->checkPhpVersion(),
            $this->checkExtensions(),
            $this->checkDirectories(),
            $this->checkEnvWritable(),
        );
    }

    public function hasCriticalFailures(): bool
    {
        foreach ($this->check() as $result) {
            if ($result['status'] === 'fail') {
                return true;
            }
        }

        return false;
    }

    private function checkPhpVersion(): array
    {
        $version = PHP_VERSION;
        $passes = version_compare($version, '8.2.0', '>=');

        return [[
            'name' => 'PHP >= 8.2',
            'status' => $passes ? 'pass' : 'fail',
            'message' => $passes
                ? 'PHP ' . $version
                : 'PHP ' . $version . ' — mindestens 8.2 erforderlich',
        ]];
    }

    private function checkExtensions(): array
    {
        $critical = ['pdo_mysql', 'gd'];
        $recommended = ['mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'sodium', 'zip'];
        $results = [];

        foreach ($critical as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => 'ext-' . $ext,
                'status' => $loaded ? 'pass' : 'fail',
                'message' => $loaded ? 'Geladen' : 'Nicht geladen (erforderlich)',
            ];
        }

        foreach ($recommended as $ext) {
            $loaded = extension_loaded($ext);
            $results[] = [
                'name' => 'ext-' . $ext,
                'status' => $loaded ? 'pass' : 'warn',
                'message' => $loaded ? 'Geladen' : 'Nicht geladen (empfohlen)',
            ];
        }

        return $results;
    }

    private function checkEnvWritable(): array
    {
        $envPath = base_path('.env');
        $writable = file_exists($envPath) && is_writable($envPath);

        return [[
            'name' => '.env',
            'status' => $writable ? 'pass' : 'warn',
            'message' => $writable
                ? 'Schreibbar'
                : 'Nicht schreibbar — bitte per FTP-Client die Berechtigung auf 664 setzen (Rechtsklick → Eigenschaften/Permissions)',
        ]];
    }

    private function checkDirectories(): array
    {
        $dirs = [
            'storage/',
            'storage/framework/sessions/',
            'storage/framework/views/',
            'storage/framework/cache/',
            'storage/logs/',
            'bootstrap/cache/',
        ];

        $results = [];

        foreach ($dirs as $dir) {
            $path = base_path($dir);
            $writable = is_dir($path) && is_writable($path);
            $results[] = [
                'name' => $dir,
                'status' => $writable ? 'pass' : 'fail',
                'message' => $writable ? 'Schreibbar' : 'Nicht schreibbar',
            ];
        }

        return $results;
    }
}
