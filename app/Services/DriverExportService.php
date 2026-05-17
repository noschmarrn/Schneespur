<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;
use ZipArchive;

class DriverExportService
{
    public function exportSingle(User $driver): string
    {
        $this->ensureZipAvailable();

        $path = tempnam(sys_get_temp_dir(), 'schneespur-export-');

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP archive');
        }

        $this->addDriverFiles($zip, $driver, '');

        $zip->close();

        return $path;
    }

    public function exportAll(): string
    {
        $this->ensureZipAvailable();

        $path = tempnam(sys_get_temp_dir(), 'schneespur-export-');

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create ZIP archive');
        }

        $drivers = User::drivers()->with('dsgvoConfirmations')->orderBy('name')->get();

        foreach ($drivers as $driver) {
            $this->addDriverFiles($zip, $driver, "fahrer-{$driver->id}/");
        }

        $zip->close();

        return $path;
    }

    private function addDriverFiles(ZipArchive $zip, User $driver, string $prefix): void
    {
        $zip->addFromString($prefix.'profile.json', json_encode([
            'id' => $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'notes' => $driver->notes,
            'created_at' => $driver->created_at?->toIso8601String(),
            'dsgvo_informed_at' => $driver->dsgvo_informed_at?->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addFromString($prefix.'dsgvo_confirmations.csv', $this->buildConfirmationsCsv($driver));

        $zip->addFromString($prefix.'jobs.csv', "id,start,end,customer,notes\n");
        $zip->addFromString($prefix.'workshifts.csv', "id,start,end,driver\n");
        $zip->addFromString($prefix.'locations.csv', "id,lat,lon,timestamp\n");
        $zip->addEmptyDir($prefix.'photos');
    }

    private function buildConfirmationsCsv(User $driver): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['confirmed_at', 'template_version', 'signed_by', 'ip_address', 'user_agent']);

        foreach ($driver->dsgvoConfirmations()->orderBy('confirmed_at')->get() as $confirmation) {
            fputcsv($stream, [
                $confirmation->confirmed_at?->toIso8601String(),
                $confirmation->template_version,
                $confirmation->signed_by,
                $confirmation->ip_address,
                $confirmation->user_agent,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function ensureZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP zip extension is required for data export');
        }
    }
}
