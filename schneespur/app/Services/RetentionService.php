<?php

namespace App\Services;

use App\Enums\JobType;
use App\Models\GpsPoint;
use App\Models\Job;
use App\Models\MonthlyStatistic;
use App\Models\Setting;
use App\Models\WorkShift;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RetentionService
{
    public function __construct(
        private readonly JobAuditService $auditService,
        private readonly StorageBackendRegistry $storageRegistry,
    ) {}

    public function getExpiredJobs(int $limit = 50): Collection
    {
        $retentionYears = (int) Setting::get('retention_years', 3);

        return Job::whereNotNull('ended_at')
            ->where('ended_at', '<', now()->subYears($retentionYears))
            ->orderBy('ended_at', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getRetentionStats(): ?object
    {
        $retentionYears = (int) Setting::get('retention_years', 3);

        $query = Job::whereNotNull('ended_at')
            ->where('ended_at', '<', now()->subYears($retentionYears));

        $count = $query->count();

        if ($count === 0) {
            return null;
        }

        $oldestDate = $query->min('ended_at');

        return (object) [
            'count' => $count,
            'oldestDate' => $oldestDate,
        ];
    }

    public function aggregateForMonth(int $year, int $month, Collection $jobs): void
    {
        $typeCounts = [
            'raumen_count' => $jobs->where('type', JobType::Raumen)->count(),
            'streuen_count' => $jobs->where('type', JobType::Streuen)->count(),
            'kontrolle_count' => $jobs->where('type', JobType::Kontrolle)->count(),
            'raumen_streuen_count' => $jobs->where('type', JobType::RaumenStreuen)->count(),
        ];

        $totalGpsPoints = $jobs->sum(fn (Job $job) => $job->gpsPoints()->count());
        $totalPhotos = $jobs->sum(fn (Job $job) => $job->jobPhotos()->count());

        $totalDurationMinutes = $jobs->sum(function (Job $job) {
            if (! $job->started_at || ! $job->ended_at) {
                return 0;
            }

            return $job->started_at->diffInMinutes($job->ended_at);
        });

        $uniqueCustomers = $jobs->pluck('customer_id')->filter()->unique()->count();
        $uniqueDrivers = $jobs->pluck('user_id')->filter()->unique()->count();
        $manualCount = $jobs->where('is_manual', true)->count();

        $temperatures = $jobs->flatMap(
            fn (Job $job) => $job->weatherSnapshots->pluck('temperature')
        )->filter(fn ($t) => $t !== null);

        $avgTemperature = $temperatures->isNotEmpty()
            ? round($temperatures->avg(), 2)
            : null;

        $existing = MonthlyStatistic::where('year', $year)->where('month', $month)->first();

        if ($existing) {
            $existing->update([
                'total_jobs' => $existing->total_jobs + $jobs->count(),
                'raumen_count' => $existing->raumen_count + $typeCounts['raumen_count'],
                'streuen_count' => $existing->streuen_count + $typeCounts['streuen_count'],
                'kontrolle_count' => $existing->kontrolle_count + $typeCounts['kontrolle_count'],
                'raumen_streuen_count' => $existing->raumen_streuen_count + $typeCounts['raumen_streuen_count'],
                'manual_count' => $existing->manual_count + $manualCount,
                'total_gps_points' => $existing->total_gps_points + $totalGpsPoints,
                'total_photos' => $existing->total_photos + $totalPhotos,
                'total_duration_minutes' => $existing->total_duration_minutes + $totalDurationMinutes,
                'avg_temperature' => $avgTemperature !== null
                    ? ($existing->avg_temperature !== null
                        ? round(($existing->avg_temperature + $avgTemperature) / 2, 2)
                        : $avgTemperature)
                    : $existing->avg_temperature,
                'unique_customers' => $existing->unique_customers + $uniqueCustomers,
                'unique_drivers' => $existing->unique_drivers + $uniqueDrivers,
            ]);
        } else {
            MonthlyStatistic::create([
                'year' => $year,
                'month' => $month,
                'total_jobs' => $jobs->count(),
                'raumen_count' => $typeCounts['raumen_count'],
                'streuen_count' => $typeCounts['streuen_count'],
                'kontrolle_count' => $typeCounts['kontrolle_count'],
                'raumen_streuen_count' => $typeCounts['raumen_streuen_count'],
                'manual_count' => $manualCount,
                'total_gps_points' => $totalGpsPoints,
                'total_photos' => $totalPhotos,
                'total_duration_minutes' => $totalDurationMinutes,
                'avg_temperature' => $avgTemperature,
                'unique_customers' => $uniqueCustomers,
                'unique_drivers' => $uniqueDrivers,
            ]);
        }
    }

    public function deleteJob(Job $job): void
    {
        $photos = $job->jobPhotos()->get();

        $active = $this->storageRegistry->resolve();
        $local = $this->storageRegistry->resolve(StorageBackendRegistry::DEFAULT_BACKEND);

        foreach ($photos as $photo) {
            $paths = array_filter([
                $photo->file_path,
                $photo->thumbnail_path,
                $photo->annotated_path,
            ]);

            foreach ($paths as $path) {
                $active->delete($path);
                if ($active->slug() !== $local->slug()) {
                    $local->delete($path);
                }
            }
        }

        GpsPoint::where('job_id', $job->id)->delete();

        $this->auditService->logDeletion($job);

        $workShiftId = $job->work_shift_id;

        $job->delete();

        if ($workShiftId) {
            $remainingJobs = Job::where('work_shift_id', $workShiftId)->count();
            if ($remainingJobs === 0) {
                WorkShift::where('id', $workShiftId)->delete();
            }
        }
    }

    public function purge(int $limit = 50): int
    {
        $jobs = $this->getExpiredJobs($limit);

        if ($jobs->isEmpty()) {
            return 0;
        }

        $jobs->load(['jobPhotos', 'gpsPoints', 'weatherSnapshots']);

        $grouped = $jobs->groupBy(fn (Job $job) => $job->ended_at->format('Y-m'));

        foreach ($grouped as $yearMonth => $monthJobs) {
            [$year, $month] = explode('-', $yearMonth);
            $this->aggregateForMonth((int) $year, (int) $month, $monthJobs);
        }

        $deleted = 0;

        foreach ($jobs as $job) {
            try {
                $this->deleteJob($job);
                $deleted++;
            } catch (\Throwable $e) {
                Log::error("Retention: Failed to delete job {$job->id}", [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deleted;
    }
}
