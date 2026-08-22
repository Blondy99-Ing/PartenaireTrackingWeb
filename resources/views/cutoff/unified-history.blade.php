@extends('layouts.app')

@section('title', 'Historique global coupure / allumage')

@section('content')
<div class="space-y-4 p-0 md:p-4">

@php
    $period = $filters['period'] ?? '';
    $source = $filters['source'] ?? '';
    $tz     = config('app.display_timezone', 'Africa/Douala');

    $toneClass = function (?string $tone) {
        return match ($tone) {
            'success', 'cut' => 'dash-badge success',
            'failed' => 'dash-badge danger',
            'pending', 'waiting', 'sent' => 'dash-badge warning',
            'cancelled' => 'dash-badge muted',
            default => 'dash-badge muted',
        };
    };

    /**
     * Métadonnées d'affichage du journal complet du cycle (lease_cutoff_events)
     * — même liste que leases/cutoff-history.blade.php.
     */
    $eventTypeMeta = [
        'WAITING_STATE_UNKNOWN'          => ['label' => 'État boîtier illisible',         'icon' => 'fa-question',          'color' => '#6b7280'],
        'WAITING_OFFLINE'                => ['label' => 'Boîtier hors-ligne',             'icon' => 'fa-plug-circle-xmark', 'color' => '#c2410c'],
        'WAITING_MOVEMENT_UNCERTAIN'     => ['label' => 'Mouvement incertain',            'icon' => 'fa-circle-question',   'color' => '#c2410c'],
        'WAITING_MOVING'                 => ['label' => 'Véhicule en mouvement',          'icon' => 'fa-gauge-high',        'color' => '#c2410c'],
        'COMMAND_SENT'                   => ['label' => 'Commande de coupure envoyée',    'icon' => 'fa-paper-plane',       'color' => '#6d28d9'],
        'COMMAND_PENDING_CONFIRMATION'   => ['label' => 'Attente confirmation moteur',    'icon' => 'fa-hourglass-half',    'color' => '#6d28d9'],
        'CUT_OFF_CONFIRMED'              => ['label' => 'Coupure moteur confirmée',       'icon' => 'fa-check',             'color' => '#047857'],
        'CUT_REFUSED_FORGIVEN'           => ['label' => 'Coupure bloquée : dossier pardonné', 'icon' => 'fa-shield-halved', 'color' => '#15803d'],
        'CUT_REFUSED_ALREADY_SENT'       => ['label' => 'Coupure non renvoyée : déjà envoyée', 'icon' => 'fa-shield-halved', 'color' => '#6d28d9'],
        'CANCELLED_PAID'                 => ['label' => 'Annulée : paiement confirmé',    'icon' => 'fa-ban',               'color' => '#4b5563'],
        'CANCELLED_UNVERIFIED'           => ['label' => 'Annulée : sans preuve paiement', 'icon' => 'fa-circle-question',   'color' => '#b45309'],
        'CANCELLED_RULE_MISSING'         => ['label' => 'Annulée : règle absente',        'icon' => 'fa-link-slash',        'color' => '#4b5563'],
        'CANCELLED_RULE_DISABLED'        => ['label' => 'Annulée : règle inactive',       'icon' => 'fa-toggle-off',        'color' => '#4b5563'],
        'REACTIVATION_CONFIRMED'         => ['label' => 'Rallumage confirmé',             'icon' => 'fa-bolt',              'color' => '#15803d'],
        'REACTIVATION_PENDING_CONFIRMATION' => ['label' => 'Attente confirmation rallumage', 'icon' => 'fa-hourglass-half', 'color' => '#6d28d9'],
        'REACTIVATION_FAILED'            => ['label' => 'Échec du rallumage',             'icon' => 'fa-xmark',             'color' => '#b91c1c'],
        'FAILED'                         => ['label' => 'Échec final',                    'icon' => 'fa-xmark',             'color' => '#b91c1c'],
    ];
@endphp

<div class="dash-top">
    <div class="dash-title">
        <h1><i class="fas fa-layer-group"></i> Historique global coupure / allumage</h1>
        <p>Toutes les coupures et tous les rallumages de la flotte, automatiques (leases) et manuels, réunis dans une seule chronologie.</p>
    </div>
</div>

{{-- KPI --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    @php
        $kpis = [
            ['label' => 'Total', 'value' => $summary['total'] ?? 0, 'icon' => 'fa-list'],
            ['label' => 'Automatique', 'value' => $summary['automatique'] ?? 0, 'icon' => 'fa-robot'],
            ['label' => 'Manuel', 'value' => $summary['manuel'] ?? 0, 'icon' => 'fa-hand'],
            ['label' => 'Coupures', 'value' => $summary['coupures'] ?? 0, 'icon' => 'fa-plug-circle-xmark'],
            ['label' => 'Rallumages', 'value' => $summary['allumages'] ?? 0, 'icon' => 'fa-bolt'],
            ['label' => 'Échecs', 'value' => $summary['echecs'] ?? 0, 'icon' => 'fa-triangle-exclamation'],
        ];
    @endphp
    @foreach($kpis as $kpi)
        <div class="ui-card p-3 text-center">
            <i class="fas {{ $kpi['icon'] }} text-secondary mb-1"></i>
            <div class="text-2xl font-bold font-orbitron" style="color: var(--color-text);">{{ number_format($kpi['value']) }}</div>
            <div class="text-[11px] text-secondary uppercase tracking-wide">{{ $kpi['label'] }}</div>
        </div>
    @endforeach
</div>

{{-- Filtres --}}
<div class="ui-card p-4">
    <form method="GET" id="unifiedFiltersForm" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="md:col-span-3 lg:col-span-2">
            <label class="text-xs text-secondary">Recherche</label>
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:.75rem;pointer-events:none;"></i>
                <input type="text" name="search" id="unifiedSearchInput" value="{{ $filters['search'] ?? '' }}" class="ui-input w-full" style="padding-left:2rem;" autocomplete="off"
                       placeholder="Immatriculation, chauffeur, contrat, lease, commande, motif…">
            </div>
        </div>

        <div>
            <label class="text-xs text-secondary">Origine</label>
            <select name="source" class="ui-input w-full" data-autosubmit>
                <option value="">Toutes</option>
                <option value="AUTOMATIQUE" @selected($source === 'AUTOMATIQUE')>Automatique (lease)</option>
                <option value="MANUEL" @selected($source === 'MANUEL')>Manuel</option>
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Type de coupure</label>
            <select name="direction" class="ui-input w-full" data-autosubmit>
                @foreach($availableDirections as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['direction'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Statut</label>
            <select name="status" class="ui-input w-full" data-autosubmit>
                @foreach($availableStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Période</label>
            <select name="period" class="ui-input w-full" id="unifiedPeriodSelect" data-autosubmit>
                <option value="">Toutes les dates</option>
                <option value="today" @selected($period === 'today')>Aujourd'hui</option>
                <option value="yesterday" @selected($period === 'yesterday')>Hier</option>
                <option value="this_week" @selected($period === 'this_week')>Cette semaine</option>
                <option value="this_month" @selected($period === 'this_month')>Ce mois-ci</option>
                <option value="this_year" @selected($period === 'this_year')>Cette année</option>
                <option value="specific_date" @selected($period === 'specific_date')>Date précise</option>
                <option value="range" @selected($period === 'range')>Plage de dates</option>
            </select>
        </div>

        <div id="unifiedSpecificDateWrap" class="{{ $period === 'specific_date' ? '' : 'hidden' }}">
            <label class="text-xs text-secondary">Date</label>
            <input type="date" name="specific_date" value="{{ $filters['specific_date'] ?? '' }}" class="ui-input w-full" data-autosubmit>
        </div>

        <div id="unifiedRangeFromWrap" class="{{ $period === 'range' ? '' : 'hidden' }}">
            <label class="text-xs text-secondary">Du</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="ui-input w-full" data-autosubmit>
        </div>

        <div id="unifiedRangeToWrap" class="{{ $period === 'range' ? '' : 'hidden' }}">
            <label class="text-xs text-secondary">Au</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="ui-input w-full" data-autosubmit>
        </div>

        <div class="flex items-end gap-2">
            <button class="btn-primary" type="submit">
                <i class="fas fa-filter mr-2"></i> Filtrer
            </button>
            <a class="btn-secondary" href="{{ route('cutoff.history.unified') }}">Réinitialiser</a>
        </div>
    </form>
</div>

{{-- Tableau --}}
<div class="ui-card">
    <h2 class="text-xl font-bold font-orbitron mb-6">Chronologie</h2>

    <div class="ui-table-container shadow-md">
        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Origine</th>
                    <th>Véhicule</th>
                    <th>Type de contrat</th>
                    <th>Action</th>
                    <th>Statut</th>
                    <th>Déclenché par</th>
                    <th>Motif</th>
                    <th class="text-center">Détail</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $index => $row)
                    @php $rowId = 'uch-row-' . $index . '-' . ($row['cmd_no'] ?? $row['timestamp']?->timestamp); @endphp
                    <tr>
                        <td class="text-sm whitespace-nowrap">
                            {{ $row['timestamp'] ? \Illuminate\Support\Carbon::parse($row['timestamp'])->timezone($tz)->format('d/m/Y H:i') : '—' }}
                        </td>

                        <td>
                            @if($row['source'] === 'AUTOMATIQUE')
                                <span class="dash-badge muted"><i class="fas fa-robot mr-1"></i> Automatique</span>
                            @else
                                <span class="dash-badge muted"><i class="fas fa-hand mr-1"></i> Manuel</span>
                            @endif
                        </td>

                        <td>
                            <div class="flex flex-col leading-tight">
                                <span class="font-semibold">{{ $row['vehicle_label'] }}</span>
                                <span class="text-xs text-secondary">{{ $row['vehicle_sub'] }}</span>
                            </div>
                        </td>

                        <td>
                            @if($row['source'] === 'AUTOMATIQUE')
                                <span class="font-semibold">{{ $row['contract_type_label'] ?? '—' }}</span>
                                <div class="text-xs text-secondary">{{ $row['contract_kind_label'] ?? '' }}</div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>

                        <td>
                            <span class="dash-badge {{ $row['direction'] === 'COUPURE' ? 'danger' : 'success' }}">
                                {{ $row['direction'] === 'COUPURE' ? 'Coupure' : 'Rallumage' }}
                            </span>
                        </td>

                        <td>
                            <span class="{{ $toneClass($row['tone']) }}">{{ $row['action_label'] }}</span>
                        </td>

                        <td class="text-sm">{{ $row['actor'] }}</td>

                        <td class="text-xs text-secondary max-w-xs">
                            {{ $row['reason'] ? \Illuminate\Support\Str::limit($row['reason'], 100) : '—' }}
                        </td>

                        <td class="text-center">
                            <button type="button" class="btn-secondary text-xs px-2 py-1" onclick="uchToggle('{{ $rowId }}')" id="uch-btn-{{ $rowId }}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                    <tr id="{{ $rowId }}" class="hidden">
                        <td colspan="9" class="p-0">
                            <div class="p-4" style="background: var(--color-bg-subtle, #f9fafb);">
                                @if($row['source'] === 'AUTOMATIQUE')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Type de contrat</div>
                                            <div class="font-semibold">{{ $row['contract_type_label'] ?? '—' }}</div>
                                            <div class="text-secondary">{{ $row['contract_kind_label'] ?? '' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Échéance du lease</div>
                                            <div class="font-semibold">{{ $row['lease_due_date'] ? \Illuminate\Support\Carbon::parse($row['lease_due_date'])->format('d/m/Y') : '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Chauffeur</div>
                                            <div class="font-semibold">{{ $row['driver_name'] ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Reste à payer</div>
                                            <div class="font-semibold">{{ $row['montant_du'] !== null ? number_format((float) $row['montant_du'], 0, ',', ' ') . ' FCFA' : '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Vitesse au contrôle</div>
                                            <div class="font-semibold">{{ $row['speed_at_check'] !== null ? $row['speed_at_check'] . ' km/h' : '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">État moteur au contrôle</div>
                                            <div class="font-semibold">{{ $row['ignition_state'] ?? '—' }}</div>
                                        </div>
                                    </div>

                                    {{-- Horodatage complet : détecté -> planifié -> commande -> confirmée. --}}
                                    <div class="mt-3">
                                        <div class="text-secondary uppercase tracking-wide mb-2" style="font-size:.65rem;">
                                            <i class="fas fa-clock mr-1"></i> Horodatage complet
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                            <div>
                                                <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Détecté</div>
                                                <div class="font-semibold">{{ $row['detected_at'] ? $row['detected_at']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Planifié</div>
                                                <div class="font-semibold">{{ $row['scheduled_for'] ? $row['scheduled_for']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Commande</div>
                                                <div class="font-semibold">{{ $row['cutoff_requested_at'] ? $row['cutoff_requested_at']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : '—' }}</div>
                                            </div>
                                            <div>
                                                <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Confirmée</div>
                                                <div class="font-semibold">{{ $row['cutoff_executed_at'] ? $row['cutoff_executed_at']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : '—' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Journal complet du cycle : explique un écart entre l'heure planifiée
                                         et l'heure réelle (véhicule offline, en mouvement, commande envoyée...).
                                         Les vérifications consécutives de MÊME type (ex. 8 "attente confirmation"
                                         d'affilée) sont regroupées en une seule ligne — sinon un cycle de 20
                                         tentatives noie l'info utile (les changements d'état) sous 20 lignes
                                         quasi identiques. Amélioration demandée le 22/08/2026. --}}
                                    @if(!empty($row['events']) && $row['events']->isNotEmpty())
                                        @php
                                            $groups = [];
                                            foreach ($row['events'] as $event) {
                                                $last = $groups ? end($groups) : null;
                                                $sameRun = $last
                                                    && $last['event_type'] === $event->event_type
                                                    && $last['ignition_state'] === $event->ignition_state;

                                                if ($sameRun) {
                                                    $groups[count($groups) - 1]['last'] = $event;
                                                    $groups[count($groups) - 1]['count']++;
                                                } else {
                                                    $groups[] = [
                                                        'event_type' => $event->event_type,
                                                        'ignition_state' => $event->ignition_state,
                                                        'first' => $event,
                                                        'last' => $event,
                                                        'count' => 1,
                                                    ];
                                                }
                                            }

                                            $firstEvt = $row['events']->first();
                                            $lastEvt = $row['events']->last();
                                            $totalMinutes = $firstEvt && $lastEvt ? (int) round($firstEvt->occurred_at->diffInMinutes($lastEvt->occurred_at)) : 0;
                                        @endphp
                                        <div class="mt-3">
                                            <div class="text-secondary uppercase tracking-wide mb-2 flex items-center gap-2 flex-wrap" style="font-size:.65rem;">
                                                <span><i class="fas fa-timeline mr-1"></i> Journal complet du cycle</span>
                                                @if($totalMinutes >= 5)
                                                    <span class="dash-badge warning" style="font-size:.65rem;">
                                                        <i class="fas fa-hourglass" style="font-size:.6rem;"></i>
                                                        écart total : {{ $totalMinutes >= 60 ? intdiv($totalMinutes, 60) . 'h' . str_pad($totalMinutes % 60, 2, '0', STR_PAD_LEFT) : $totalMinutes . ' min' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="space-y-2">
                                                @foreach($groups as $group)
                                                    @php
                                                        $meta = $eventTypeMeta[$group['event_type']] ?? ['label' => $group['event_type'], 'icon' => 'fa-circle', 'color' => '#6b7280'];
                                                    @endphp
                                                    <div class="flex items-start gap-2 text-xs">
                                                        <i class="fas {{ $meta['icon'] }} mt-0.5" style="color: {{ $meta['color'] }}; width: 14px;"></i>
                                                        <div class="flex-1">
                                                            <div class="flex items-center gap-2 flex-wrap">
                                                                <span class="font-semibold">{{ $meta['label'] }}</span>
                                                                @if($group['count'] > 1)
                                                                    <span class="dash-badge muted" style="font-size:.65rem;">×{{ $group['count'] }}</span>
                                                                    <span class="text-secondary">{{ $group['first']->occurred_at?->copy()->setTimezone($tz)->format('H:i:s') }} → {{ $group['last']->occurred_at?->copy()->setTimezone($tz)->format('H:i:s') }}</span>
                                                                @else
                                                                    <span class="text-secondary">{{ $group['first']->occurred_at?->copy()->setTimezone($tz)->format('d/m/Y H:i:s') }}</span>
                                                                @endif
                                                                @if($group['last']->speed_at_check !== null)
                                                                    <span class="dash-badge muted"><i class="fas fa-gauge" style="font-size:.6rem;"></i> {{ $group['last']->speed_at_check }} km/h</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-secondary">{{ $group['last']->message }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">N° de commande</div>
                                            <div class="font-semibold font-mono">{{ $row['cmd_no'] ?? '—' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Chauffeur actuel du véhicule</div>
                                            <div class="font-semibold">{{ $row['driver_name'] ?? '—' }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if($row['reason'])
                                    <div class="mt-3 text-xs">
                                        <div class="text-secondary uppercase tracking-wide" style="font-size:.65rem;">Motif complet</div>
                                        <div>{{ $row['reason'] }}</div>
                                    </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-6">Aucun événement pour cette période.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $history->appends(request()->query())->links() }}
    </div>
</div>

</div>

<script>
function uchToggle(rowId) {
    const row = document.getElementById(rowId);
    const btn = document.getElementById('uch-btn-' + rowId);
    if (!row || !btn) return;
    const isHidden = row.classList.contains('hidden');
    row.classList.toggle('hidden', !isHidden);
    btn.querySelector('i').classList.toggle('fa-chevron-down', !isHidden);
    btn.querySelector('i').classList.toggle('fa-chevron-up', isHidden);
}

(function () {
    const form          = document.getElementById('unifiedFiltersForm');
    const periodSelect  = document.getElementById('unifiedPeriodSelect');
    const specificWrap  = document.getElementById('unifiedSpecificDateWrap');
    const fromWrap      = document.getElementById('unifiedRangeFromWrap');
    const toWrap        = document.getElementById('unifiedRangeToWrap');
    const searchInput   = document.getElementById('unifiedSearchInput');

    function sync() {
        if (!periodSelect) return;
        const val = periodSelect.value;
        specificWrap?.classList.toggle('hidden', val !== 'specific_date');
        fromWrap?.classList.toggle('hidden', val !== 'range');
        toWrap?.classList.toggle('hidden', val !== 'range');
    }

    if (periodSelect) {
        periodSelect.addEventListener('change', sync);
        sync();
    }

    if (form) {
        // Recherche : soumission automatique après une courte pause de frappe.
        if (searchInput) {
            let timer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { form.submit(); }, 600);
            });
        }

        // Selects / dates : soumission immédiate au changement.
        form.querySelectorAll('[data-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () { form.submit(); });
        });
    }
})();
</script>
@endsection
