<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobPhoto;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PhotoService
{
    public const MAX_PHOTOS_PER_JOB = 5;

    private const MAX_ORIGINAL_PX = 1920;

    private const THUMB_WIDTH_PX = 300;

    private const JPEG_QUALITY = 80;

    public function __construct(
        private readonly StorageBackendRegistry $storageRegistry,
    ) {}

    public function store(UploadedFile $file, Job $job): JobPhoto
    {
        $uuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';

        $originalRelPath = "photos/{$uuid}.{$ext}";
        $thumbRelPath = "photos/thumbs/{$uuid}.{$ext}";
        $annotatedRelPath = "photos/annotated/{$uuid}.jpg";

        $image = Image::decode($file->getContent());
        $image->orient();

        $image->scaleDown(width: self::MAX_ORIGINAL_PX, height: self::MAX_ORIGINAL_PX);

        $backend = $this->storageRegistry->resolve();
        $encodedOriginal = (string) $image->encodeUsingMediaType($this->mediaType($ext), quality: self::JPEG_QUALITY);
        $backend->store($originalRelPath, $encodedOriginal);

        $image->scaleDown(width: self::THUMB_WIDTH_PX);
        $backend->store($thumbRelPath, (string) $image->encodeUsingMediaType($this->mediaType($ext), quality: self::JPEG_QUALITY));

        $annotationService = app(PhotoAnnotationService::class);
        try {
            $annotatedContent = $annotationService->annotate($encodedOriginal, $job);
            $backend->store($annotatedRelPath, $annotatedContent);
        } catch (\Throwable $e) {
            Log::warning('Photo annotation failed, continuing without annotated version', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
            $annotatedRelPath = null;
        }

        Log::info('Photo stored', [
            'job_id' => $job->id,
            'file' => $originalRelPath,
            'thumb' => $thumbRelPath,
            'annotated' => $annotatedRelPath,
            'backend' => $backend->slug(),
        ]);

        return JobPhoto::create([
            'job_id' => $job->id,
            'file_path' => $originalRelPath,
            'thumbnail_path' => $thumbRelPath,
            'annotated_path' => $annotatedRelPath,
        ]);
    }

    public static function canAddPhoto(Job $job): bool
    {
        return $job->jobPhotos()->count() < self::MAX_PHOTOS_PER_JOB;
    }

    private function mediaType(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
