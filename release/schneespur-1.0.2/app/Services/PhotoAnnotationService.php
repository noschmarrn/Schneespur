<?php

namespace App\Services;

use App\Models\Job;

class PhotoAnnotationService
{
    private const FONT_SIZE = 14;

    private const LINE_HEIGHT = 22;

    private const PADDING = 16;

    private const STRIP_BG = [255, 255, 255];

    private const TEXT_COLOR = [40, 40, 40];

    private const LABEL_COLOR = [120, 120, 120];

    public function annotate(string $imageContent, Job $job): string
    {
        $source = imagecreatefromstring($imageContent);
        if ($source === false) {
            throw new \RuntimeException('Failed to decode image for annotation.');
        }

        $job->loadMissing(['customer', 'weatherSnapshots']);

        $lines = $this->buildMetadataLines($job);

        $fontPath = resource_path('fonts/DejaVuSans.ttf');
        if (! file_exists($fontPath)) {
            imagedestroy($source);
            throw new \RuntimeException('DejaVu Sans font not found at: ' . $fontPath);
        }

        $stripHeight = self::PADDING + (count($lines) * self::LINE_HEIGHT) + self::PADDING;
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        $canvas = imagecreatetruecolor($srcWidth, $srcHeight + $stripHeight);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('Failed to create annotation canvas.');
        }

        imagecopy($canvas, $source, 0, 0, 0, 0, $srcWidth, $srcHeight);
        imagedestroy($source);

        $bgColor = imagecolorallocate($canvas, ...self::STRIP_BG);
        imagefilledrectangle($canvas, 0, $srcHeight, $srcWidth - 1, $srcHeight + $stripHeight - 1, $bgColor);

        $borderColor = imagecolorallocate($canvas, 200, 200, 200);
        imageline($canvas, 0, $srcHeight, $srcWidth - 1, $srcHeight, $borderColor);

        $textColor = imagecolorallocate($canvas, ...self::TEXT_COLOR);
        $labelColor = imagecolorallocate($canvas, ...self::LABEL_COLOR);

        $y = $srcHeight + self::PADDING + self::FONT_SIZE;
        foreach ($lines as $line) {
            if (str_contains($line, ': ')) {
                [$label, $value] = explode(': ', $line, 2);

                imagettftext($canvas, self::FONT_SIZE, 0, self::PADDING, $y, $labelColor, $fontPath, $label . ': ');

                $labelBox = imagettfbbox(self::FONT_SIZE, 0, $fontPath, $label . ': ');
                $labelWidth = abs($labelBox[2] - $labelBox[0]);

                imagettftext($canvas, self::FONT_SIZE, 0, self::PADDING + $labelWidth, $y, $textColor, $fontPath, $value);
            } else {
                imagettftext($canvas, self::FONT_SIZE, 0, self::PADDING, $y, $textColor, $fontPath, $line);
            }

            $y += self::LINE_HEIGHT;
        }

        ob_start();
        imagejpeg($canvas, null, 90);
        $output = ob_get_clean();
        imagedestroy($canvas);

        return $output;
    }

    private function buildMetadataLines(Job $job): array
    {
        $lines = [];

        $lines[] = 'Einsatz: #' . $job->id . ' — ' . ($job->type?->label() ?? '');

        $object = $job->customerObject;
        $customerLine = $job->customer?->name ?? '';
        if ($object?->street) {
            $customerLine .= ', ' . $object->street;
        }
        if ($object?->zip || $object?->city) {
            $customerLine .= ', ' . trim(($object->zip ?? '') . ' ' . ($object->city ?? ''));
        }
        if ($object?->name && $object->name !== $job->customer?->name) {
            $customerLine .= ' (' . $object->name . ')';
        }
        $lines[] = 'Kunde: ' . $customerLine;

        $lines[] = 'Datum: ' . ($job->started_at?->format('d.m.Y H:i') ?? '');

        $weather = $job->weatherSnapshots->first();
        if ($weather && $weather->fetched_at) {
            $lines[] = 'Wetter: ' . $weather->temperature . '°C, '
                . $weather->precipitation . 'mm, '
                . $weather->snow_depth . 'cm Schnee';
        }

        if ($object?->lat && $object?->lon) {
            $lines[] = 'GPS: ' . $object->lat . ', ' . $object->lon;
        }

        return $lines;
    }
}
