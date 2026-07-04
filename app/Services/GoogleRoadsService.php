<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleRoadsService
{
    private string $apiKey;
    private string $snapUrl = 'https://roads.googleapis.com/v1/snapToRoads';

    public function __construct()
    {
        $this->apiKey = (string) config('services.google_maps.key');
    }

    public function cleanRawTrack(array $points): array
    {
        $clean = [];
        $prev = null;

        foreach ($points as $p) {
            $lat = isset($p['lat']) && is_numeric($p['lat']) ? (float) $p['lat'] : null;
            $lng = isset($p['lng']) && is_numeric($p['lng']) ? (float) $p['lng'] : null;
            $tsMs = isset($p['ts_ms']) && is_numeric($p['ts_ms']) ? (int) $p['ts_ms'] : null;
            $speed = isset($p['speed']) && is_numeric($p['speed']) ? (float) $p['speed'] : 0.0;

            if ($lat === null || $lng === null) {
                continue;
            }

            if ($prev) {
                $dist = $this->distanceMeters($prev['lat'], $prev['lng'], $lat, $lng);
                $dt = ($tsMs && $prev['ts_ms']) ? max(1, ($tsMs - $prev['ts_ms']) / 1000) : null;

                if ($dist < 3) {
                    continue;
                }

                if ($dt !== null) {
                    $kmh = ($dist / $dt) * 3.6;
                    if ($kmh > 160) {
                        continue;
                    }
                }

                if ($dist < 8 && $speed < 3) {
                    continue;
                }
            }

            $row = [
                'lat' => $lat,
                'lng' => $lng,
                'ts' => $p['ts'] ?? null,
                'ts_ms' => $tsMs,
                'speed' => $speed,
                'direction' => isset($p['direction']) && is_numeric($p['direction']) ? (float) $p['direction'] : null,
            ];

            $clean[] = $row;
            $prev = $row;
        }

        return array_values($clean);
    }

    public function snapTrack(array $points, bool $interpolate = true): array
    {
        $points = $this->cleanRawTrack($points);

        if (count($points) < 2 || $this->apiKey === '') {
            return $points;
        }

        $chunks = $this->chunkWithOverlap($points, 100, 2);
        $snapped = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $path = implode('|', array_map(
                fn ($p) => $p['lat'] . ',' . $p['lng'],
                $chunk
            ));

            $response = Http::timeout(20)->get($this->snapUrl, [
                'path' => $path,
                'interpolate' => $interpolate ? 'true' : 'false',
                'key' => $this->apiKey,
            ]);

            if (!$response->successful()) {
                continue;
            }

            $data = $response->json();
            $rows = $data['snappedPoints'] ?? [];

            $mapped = array_map(function ($row) {
                return [
                    'lat' => (float) data_get($row, 'location.latitude'),
                    'lng' => (float) data_get($row, 'location.longitude'),
                    'place_id' => data_get($row, 'placeId'),
                    'original_index' => data_get($row, 'originalIndex'),
                ];
            }, $rows);

            $snapped = array_merge($snapped, $mapped);
        }

        return $this->dedupeSnapped($snapped);
    }

    private function chunkWithOverlap(array $points, int $size = 100, int $overlap = 2): array
    {
        $chunks = [];
        $count = count($points);
        $start = 0;

        while ($start < $count) {
            $slice = array_slice($points, $start, $size);

            if (!empty($slice)) {
                $chunks[] = $slice;
            }

            if (($start + $size) >= $count) {
                break;
            }

            $start += max(1, $size - $overlap);
        }

        return $chunks;
    }

    private function dedupeSnapped(array $points): array
    {
        $out = [];
        $seen = [];

        foreach ($points as $p) {
            $key = round($p['lat'], 6) . '|' . round($p['lng'], 6) . '|' . ($p['place_id'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $p;
        }

        return $out;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth * $c;
    }
}