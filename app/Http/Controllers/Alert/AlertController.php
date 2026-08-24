<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertController extends Controller
{
    private array $statsTypes = ['stolen','geofence','speed','safe_zone','time_zone'];

    private array $visibleAlertTypes = [
        'stolen',
        'geofence',
        'geo_fence',
        'speed',
        'overspeed',
        'speeding',
        'safe_zone',
        'time_zone',
        'timezone',
    ];

    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();

        $vehicleIds = DB::table('association_user_voitures')
            ->where('user_id', $partnerId)
            ->pluck('voiture_id')
            ->toArray();

        if (empty($vehicleIds)) {
            return $this->emptyResponse($request);
        }

        $query = DB::table('alerts as a')
            ->join('voitures as v', 'v.id', '=', 'a.voiture_id')
            ->leftJoin(DB::raw('(SELECT MAX(id) as max_id, voiture_id FROM association_chauffeur_voiture_partner GROUP BY voiture_id) as last_assign'), 'last_assign.voiture_id', '=', 'a.voiture_id')
            ->leftJoin('association_chauffeur_voiture_partner as acvp', 'acvp.id', '=', 'last_assign.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp.chauffeur_id')
            ->whereIn('a.voiture_id', $vehicleIds)
            ->whereIn('a.alert_type', $this->visibleAlertTypes);

        $this->applyFilters($query, $request);

        $stats = $this->computeStats(clone $query);

        $perPage = (int) $request->query('per_page', 50);
        $alerts = $query->orderByDesc('a.id')
            ->select([
                'a.*',
                'v.immatriculation', 'v.marque', 'v.model',
                'u.nom as driver_nom', 'u.prenom as driver_prenom'
            ])
            ->paginate(min($perPage, 200));

        return response()->json([
            'status' => 'success',
            'data'   => collect($alerts->items())->map(fn($r) => $this->formatAlert($r)),
            'meta'   => [
                'current_page' => $alerts->currentPage(),
                'total'        => $alerts->total(),
                'last_page'    => $alerts->lastPage(),
            ],
            'stats'  => $stats
        ]);
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('q')) {
            $term = "%{$request->q}%";
            $query->where(function($q) use ($term) {
                $q->where('v.immatriculation', 'like', $term)
                  ->orWhere('a.message', 'like', $term)
                  ->orWhere('u.nom', 'like', $term);
            });
        }

        if ($request->filled('alert_type') && $request->alert_type !== 'all') {
            $requestedType = strtolower(trim($request->alert_type));

            match ($requestedType) {
                'speed' => $query->whereIn('a.alert_type', ['speed', 'overspeed', 'speeding']),
                'geofence' => $query->whereIn('a.alert_type', ['geofence', 'geo_fence']),
                'time_zone' => $query->whereIn('a.alert_type', ['time_zone', 'timezone']),
                default => $query->where('a.alert_type', $requestedType),
            };
        }

        /**
         * Anomalie corrigée : les bornes étaient calculées en heure Douala
         * (now($tz), Carbon::parse($request->date_from) sans reconversion)
         * puis liées TELLES QUELLES dans whereDate/whereBetween contre
         * a.created_at (colonne DATETIME, littéral UTC stocké). Laravel ne
         * reconvertit jamais un DateTimeInterface en UTC avant de le lier à
         * une requête SQL (Illuminate\Database\Connection::prepareBindings()
         * réémet juste les chiffres du fuseau déjà attaché à l'objet) : le
         * filtre "aujourd'hui" sélectionnait donc les mauvaises lignes,
         * décalé d'environ 1h. LocalTime::periodRange() calcule la période
         * en heure Douala PUIS la reconvertit en UTC avant de la retourner.
         * Trouvé et corrigé le 24/08/2026.
         */
        $quick = $request->get('quick') ?: $request->get('date_quick', 'today');

        if ($quick && $quick !== 'range') {
            [$from, $to] = LocalTime::periodRange($quick);
        } elseif ($request->filled('date_from')) {
            [$from, $to] = LocalTime::periodRange('range', [
                'from' => $request->date_from,
                'to' => $request->date_to ?? $request->date_from,
            ]);
        } else {
            [$from, $to] = [null, null];
        }

        if ($from && $to) {
            $query->whereBetween('a.created_at', [$from, $to]);
        }
    }

    private function computeStats($query)
    {
        $rows = $query->selectRaw("alert_type, COUNT(*) as count")
                      ->groupBy('alert_type')
                      ->get();

        $byType = array_fill_keys($this->statsTypes, 0);

        foreach ($rows as $row) {
            $type = $this->normalizeType($row->alert_type);
            if (isset($byType[$type])) {
                $byType[$type] += (int) $row->count;
            }
        }

        return ['by_type' => $byType];
    }

    private function formatAlert($r): array
    {
        /**
         * alerted_at peut être vide selon l'origine de l'alerte (certains
         * types ne le renseignent pas) : on retombe alors sur created_at,
         * toujours présent. Les deux sont des colonnes DATETIME (littéral
         * UTC), donc lues via displayRaw() plutôt que display().
         *
         * alerted_at (ISO, suffixe Z explicite) est fourni pour un usage JS
         * éventuel (new Date(...)) : le suffixe Z force le navigateur à
         * l'interpréter comme UTC avant de le reconvertir dans son propre
         * fuseau, au lieu de le lire à tort comme une heure locale du poste
         * client. alerted_at_human est la même valeur déjà formatée en heure
         * de Douala, prête à afficher sans recalcul côté client.
         *
         * Corrige au passage un bug fonctionnel sans lien avec le fuseau :
         * alerts/index.blade.php attendait déjà ces deux clés (isToday(),
         * colonne date de la table), jamais envoyées par ce contrôleur — la
         * colonne date et le filtre "aujourd'hui" de cette page étaient donc
         * cassés. Trouvé et corrigé le 24/08/2026.
         */
        $rawAlertedAt = $r->alerted_at ?? $r->created_at;

        return [
            'id'               => (int) $r->id,
            'type'             => $this->normalizeType($r->alert_type),
            'message'          => $r->message,
            'is_read'          => (bool) $r->read,
            'is_processed'     => (bool) ($r->processed ?? false),
            'alerted_at'       => $rawAlertedAt
                ? Carbon::createFromFormat('Y-m-d H:i:s', $rawAlertedAt, 'UTC')->format('Y-m-d\TH:i:s\Z')
                : null,
            'alerted_at_human' => LocalTime::displayRaw($rawAlertedAt, 'd/m/Y H:i:s'),
            'created_at'       => LocalTime::displayRaw($r->created_at, 'd/m/Y H:i:s'),
            'vehicle'          => [
                'id'    => $r->voiture_id,
                'label' => $r->immatriculation . " (" . $r->marque . ")",
            ],
            'driver'           => trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? '')) ?: 'Non assigné'
        ];
    }

    private function normalizeType(?string $t): string
    {
        return match (strtolower(trim((string)$t))) {
            'overspeed', 'speeding' => 'speed',
            'geo_fence'             => 'geofence',
            'timezone'              => 'time_zone',
            default                 => $t ?: 'unknown',
        };
    }

    private function emptyResponse($request) {
        return response()->json([
            'status' => 'success', 'data' => [],
            'meta' => ['total' => 0, 'current_page' => 1],
            'stats' => ['by_type' => array_fill_keys($this->statsTypes, 0)]
        ]);
    }
}