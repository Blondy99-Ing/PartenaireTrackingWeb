<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Collage des points GPS sur la route réelle, sans dépendre d'un
 * fournisseur payant (Google Roads a été trouvé désactivé/non
 * fonctionnel sur ce projet).
 *
 * Source : OpenStreetMap (données libres, licence ODbL), interrogée via
 * l'API Overpass, uniquement pour la zone couverte par le trajet demandé
 * (pas tout le Cameroun d'un coup) — chaque tuile de 0,1° x 0,1°
 * (~11 km x 11 km) est mise en cache localement dès sa première
 * consultation, donc les trajets suivants dans la même zone ne
 * re-téléchargent rien.
 *
 * Méthode : "point le plus proche sur le segment de route le plus
 * proche" — pas un vrai map-matching qui suivrait le graphe routier
 * étape par étape (beaucoup plus complexe). Suffisant pour corriger le
 * bruit GPS habituel ; peut occasionnellement rester imprécis sur un
 * pont, un rond-point serré ou une zone sans route cartographiée.
 */
class ManualRoadSnapService
{
    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    /** Types de voies pertinents pour un véhicule (on exclut trottoirs, sentiers, escaliers...). */
    private const HIGHWAY_TAGS = 'motorway|motorway_link|trunk|trunk_link|primary|primary_link|secondary|secondary_link|tertiary|tertiary_link|unclassified|residential|service|track';

    private const TILE_SIZE_DEG = 0.1;
    private const BBOX_MARGIN_DEG = 0.01;
    private const CACHE_TTL_DAYS = 30;
    private const MAX_SNAP_DISTANCE_METERS = 60.0;

    /**
     * Nettoyage du bruit GPS brut (doublons, sauts de vitesse
     * irréalistes) — même logique que GoogleRoadsService::cleanRawTrack,
     * dupliquée volontairement pour que ce service reste autonome et ne
     * dépende pas d'un service tiers voué à être retiré.
     */
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
                $dist = $this->haversineMeters($prev['lat'], $prev['lng'], $lat, $lng);
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

    /**
     * Retourne une liste de points {lat, lng, original_index} — même
     * forme que GoogleRoadsService::snapTrack() pour rester interchangeable
     * côté contrôleur. Retourne un tableau vide si aucune route locale
     * n'a pu être chargée (le contrôleur retombe alors sur les points bruts).
     */
    public function snapTrack(array $points): array
    {
        $cleaned = $this->cleanRawTrack($points);

        if (count($cleaned) < 2) {
            return [];
        }

        $bbox = $this->boundingBox($cleaned);
        $segments = $this->fetchRoadSegments($bbox);

        if (empty($segments)) {
            Log::info('[MANUAL_ROAD_SNAP] Aucun segment de route trouvé pour cette zone.', ['bbox' => $bbox]);

            return [];
        }

        $snapped = [];

        foreach ($cleaned as $index => $p) {
            $result = $this->nearestPointOnSegments($p['lat'], $p['lng'], $segments);

            $snapped[] = [
                'lat' => $result['lat'] ?? $p['lat'],
                'lng' => $result['lng'] ?? $p['lng'],
                'original_index' => $index,
            ];
        }

        return $snapped;
    }

    private function boundingBox(array $points): array
    {
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'south' => min($lats) - self::BBOX_MARGIN_DEG,
            'north' => max($lats) + self::BBOX_MARGIN_DEG,
            'west'  => min($lngs) - self::BBOX_MARGIN_DEG,
            'east'  => max($lngs) + self::BBOX_MARGIN_DEG,
        ];
    }

    /**
     * Découpe la zone du trajet en tuiles fixes (grille indépendante du
     * trajet lui-même), pour que deux trajets dans la même zone partagent
     * exactement le même cache — au lieu de refaire une requête légèrement
     * différente à chaque fois.
     */
    private function fetchRoadSegments(array $bbox): array
    {
        $segments = [];
        $tileSize = self::TILE_SIZE_DEG;

        $southIdx = (int) floor($bbox['south'] / $tileSize);
        $northIdx = (int) floor($bbox['north'] / $tileSize);
        $westIdx = (int) floor($bbox['west'] / $tileSize);
        $eastIdx = (int) floor($bbox['east'] / $tileSize);

        for ($latIdx = $southIdx; $latIdx <= $northIdx; $latIdx++) {
            for ($lngIdx = $westIdx; $lngIdx <= $eastIdx; $lngIdx++) {
                $segments = array_merge($segments, $this->fetchTile($latIdx * $tileSize, $lngIdx * $tileSize));
            }
        }

        return $segments;
    }

    private function fetchTile(float $south, float $west): array
    {
        $cacheKey = sprintf('osm_road_tile_%.3f_%.3f', $south, $west);

        return Cache::remember($cacheKey, now()->addDays(self::CACHE_TTL_DAYS), function () use ($south, $west) {
            return $this->queryOverpassTile($south, $west, $south + self::TILE_SIZE_DEG, $west + self::TILE_SIZE_DEG);
        });
    }

    private function queryOverpassTile(float $south, float $west, float $north, float $east): array
    {
        $query = sprintf(
            '[out:json][timeout:25];way["highway"~"^(%s)$"](%F,%F,%F,%F);out geom;',
            self::HIGHWAY_TAGS,
            $south,
            $west,
            $north,
            $east
        );

        try {
            /**
             * Overpass rejette (406) les requêtes portant le User-Agent
             * générique par défaut de Guzzle/Laravel — mesure anti-abus
             * classique des API publiques. Un User-Agent identifiable et
             * contactable suffit à passer.
             *
             * L'instance publique gratuite répond parfois "503/504 serveur
             * trop chargé" de façon transitoire : 2 nouvelles tentatives
             * avec un court délai suffisent presque toujours.
             */
            $response = Http::timeout(30)
                ->withUserAgent('FleetraTrajetReplay/1.0 (+it_management@proxymgroup.com)')
                ->retry(2, 1500, throw: false)
                ->asForm()
                ->post(self::OVERPASS_URL, ['data' => $query]);
        } catch (\Throwable $e) {
            Log::warning('[MANUAL_ROAD_SNAP] Overpass injoignable.', [
                'bbox' => compact('south', 'west', 'north', 'east'),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('[MANUAL_ROAD_SNAP] Overpass a répondu en erreur.', [
                'bbox' => compact('south', 'west', 'north', 'east'),
                'status' => $response->status(),
            ]);

            return [];
        }

        $elements = $response->json('elements') ?? [];
        $segments = [];

        foreach ($elements as $way) {
            $geometry = $way['geometry'] ?? [];
            $count = count($geometry);

            for ($i = 0; $i < $count - 1; $i++) {
                $a = $geometry[$i];
                $b = $geometry[$i + 1];

                if (! isset($a['lat'], $a['lon'], $b['lat'], $b['lon'])) {
                    continue;
                }

                $segments[] = [
                    'lat1' => (float) $a['lat'],
                    'lng1' => (float) $a['lon'],
                    'lat2' => (float) $b['lat'],
                    'lng2' => (float) $b['lon'],
                ];
            }
        }

        return $segments;
    }

    /**
     * Point le plus proche, tous segments confondus, avec projection
     * plate locale (précise sur de si courtes distances — quelques
     * dizaines de mètres au plus) plutôt qu'un calcul géodésique complet,
     * inutilement coûteux ici.
     */
    private function nearestPointOnSegments(float $lat, float $lng, array $segments): ?array
    {
        $mpdLat = 110540.0;
        $mpdLng = 111320.0 * cos(deg2rad($lat));

        $toXY = fn (float $la, float $ln) => [($ln - $lng) * $mpdLng, ($la - $lat) * $mpdLat];

        [$px, $py] = [0.0, 0.0]; // le point lui-même est l'origine locale

        $bestDist = PHP_FLOAT_MAX;
        $bestX = null;
        $bestY = null;

        foreach ($segments as $seg) {
            [$x1, $y1] = $toXY($seg['lat1'], $seg['lng1']);
            [$x2, $y2] = $toXY($seg['lat2'], $seg['lng2']);

            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $lenSq = $dx * $dx + $dy * $dy;

            if ($lenSq < 1e-6) {
                $nx = $x1;
                $ny = $y1;
            } else {
                $t = (($px - $x1) * $dx + ($py - $y1) * $dy) / $lenSq;
                $t = max(0.0, min(1.0, $t));
                $nx = $x1 + $t * $dx;
                $ny = $y1 + $t * $dy;
            }

            $dist = hypot($px - $nx, $py - $ny);

            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestX = $nx;
                $bestY = $ny;
            }
        }

        if ($bestX === null || $bestDist > self::MAX_SNAP_DISTANCE_METERS) {
            return null;
        }

        return [
            'lat' => $lat + ($bestY / $mpdLat),
            'lng' => $lng + ($bestX / $mpdLng),
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
