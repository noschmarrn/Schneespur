<?php

namespace App\Services;

use Illuminate\Support\Collection;

class GpsSmoothingService
{
    /**
     * Two-pass GPS smoothing for display:
     * 1. Collapse stationary clusters (speed < threshold) to centroid
     * 2. Douglas-Peucker line simplification to remove redundant points
     *
     * Rohdaten in DB bleiben unverändert.
     */
    public function smooth(
        Collection $gpsPoints,
        float $speedThreshold = 0.8,
        int $minClusterSize = 3,
        float $dpToleranceMeters = 25.0,
        float $proximityRadiusMeters = 50.0,
    ): Collection {
        if ($gpsPoints->count() < $minClusterSize) {
            return $gpsPoints;
        }

        $afterSpeed = $this->collapseStationaryClusters($gpsPoints, $speedThreshold, $minClusterSize);
        $afterProximity = $this->collapseProximityClusters($afterSpeed, $proximityRadiusMeters, $minClusterSize);

        if ($afterProximity->count() <= 2) {
            return $afterProximity;
        }

        return $this->douglasPeucker($afterProximity->values()->all(), $dpToleranceMeters);
    }

    private function collapseStationaryClusters(Collection $gpsPoints, float $speedThreshold, int $minClusterSize): Collection
    {
        $points = $gpsPoints->values()->all();
        $total = count($points);

        $isStationary = array_fill(0, $total, false);

        for ($i = 1; $i < $total; $i++) {
            $dt = $points[$i]->timestamp - $points[$i - 1]->timestamp;
            if ($dt <= 0) {
                $isStationary[$i] = $isStationary[$i - 1];
                continue;
            }
            $dist = $this->haversineMeters(
                $points[$i - 1]->lat, $points[$i - 1]->lon,
                $points[$i]->lat, $points[$i]->lon
            );
            $isStationary[$i] = ($dist / $dt) < $speedThreshold;
        }

        $result = [];
        $i = 0;

        while ($i < $total) {
            if (! $isStationary[$i]) {
                $result[] = $points[$i];
                $i++;
                continue;
            }

            $cluster = [$points[$i]];
            $j = $i + 1;
            while ($j < $total && $isStationary[$j]) {
                $cluster[] = $points[$j];
                $j++;
            }

            if (count($cluster) >= $minClusterSize) {
                $result[] = $this->centroid($cluster);
            } else {
                foreach ($cluster as $p) {
                    $result[] = $p;
                }
            }

            $i = $j;
        }

        return collect($result);
    }

    private function collapseProximityClusters(Collection $points, float $radiusMeters, int $minSize): Collection
    {
        $pts = $points->values()->all();
        $total = count($pts);
        if ($total < $minSize) {
            return $points;
        }

        $result = [];
        $i = 0;

        while ($i < $total) {
            $anchor = $pts[$i];
            $cluster = [$anchor];
            $sumLat = $anchor->lat;
            $sumLon = $anchor->lon;
            $j = $i + 1;

            while ($j < $total) {
                $centroidLat = $sumLat / count($cluster);
                $centroidLon = $sumLon / count($cluster);
                $dist = $this->haversineMeters($centroidLat, $centroidLon, $pts[$j]->lat, $pts[$j]->lon);

                if ($dist <= $radiusMeters) {
                    $cluster[] = $pts[$j];
                    $sumLat += $pts[$j]->lat;
                    $sumLon += $pts[$j]->lon;
                    $j++;
                } else {
                    break;
                }
            }

            if (count($cluster) >= $minSize) {
                $result[] = $this->centroid($cluster);
            } else {
                foreach ($cluster as $p) {
                    $result[] = $p;
                }
            }

            $i = $j;
        }

        return collect($result);
    }

    private function douglasPeucker(array $points, float $toleranceMeters): Collection
    {
        $total = count($points);
        if ($total <= 2) {
            return collect($points);
        }

        $keep = array_fill(0, $total, false);
        $keep[0] = true;
        $keep[$total - 1] = true;

        $stack = [[0, $total - 1]];

        while (! empty($stack)) {
            [$start, $end] = array_pop($stack);
            $maxDist = 0;
            $maxIdx = $start;

            for ($i = $start + 1; $i < $end; $i++) {
                $dist = $this->perpendicularDistance($points[$i], $points[$start], $points[$end]);
                if ($dist > $maxDist) {
                    $maxDist = $dist;
                    $maxIdx = $i;
                }
            }

            if ($maxDist > $toleranceMeters) {
                $keep[$maxIdx] = true;
                $stack[] = [$start, $maxIdx];
                $stack[] = [$maxIdx, $end];
            }
        }

        $result = [];
        for ($i = 0; $i < $total; $i++) {
            if ($keep[$i]) {
                $result[] = $points[$i];
            }
        }

        return collect($result);
    }

    private function perpendicularDistance(object $point, object $lineStart, object $lineEnd): float
    {
        $dTotal = $this->haversineMeters($lineStart->lat, $lineStart->lon, $lineEnd->lat, $lineEnd->lon);

        if ($dTotal < 0.01) {
            return $this->haversineMeters($point->lat, $point->lon, $lineStart->lat, $lineStart->lon);
        }

        $dStartPoint = $this->haversineMeters($lineStart->lat, $lineStart->lon, $point->lat, $point->lon);
        $dEndPoint = $this->haversineMeters($lineEnd->lat, $lineEnd->lon, $point->lat, $point->lon);

        $s = ($dTotal + $dStartPoint + $dEndPoint) / 2;
        $area = sqrt(max(0, $s * ($s - $dTotal) * ($s - $dStartPoint) * ($s - $dEndPoint)));

        return (2 * $area) / $dTotal;
    }

    private function centroid(array $cluster): object
    {
        $count = count($cluster);
        $sumLat = 0;
        $sumLon = 0;

        foreach ($cluster as $p) {
            $sumLat += $p->lat;
            $sumLon += $p->lon;
        }

        $first = $cluster[0];
        $last = $cluster[$count - 1];

        return (object) [
            'lat' => $sumLat / $count,
            'lon' => $sumLon / $count,
            'timestamp' => $first->timestamp,
            'id' => $first->id ?? null,
        ];
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
