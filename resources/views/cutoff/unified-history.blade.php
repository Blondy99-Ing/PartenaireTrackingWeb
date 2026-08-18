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
<p class="text-xs text-secondary">
    <i class="fas fa-circle-info mr-1"></i>
    Les chiffres ci-dessus comptent tous les événements correspondant aux filtres. La liste ci-dessous se limite aux 500 événements les plus récents par origine — filtrez par période pour consulter un historique plus ancien.
</p>

{{-- Filtres --}}
<div class="ui-card p-4">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="md:col-span-3 lg:col-span-2">
            <label class="text-xs text-secondary">Recherche</label>
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:.75rem;pointer-events:none;"></i>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ui-input w-full" style="padding-left:2rem;"
                       placeholder="Immatriculation, chauffeur, contrat, lease, commande, motif…">
            </div>
        </div>

        <div>
            <label class="text-xs text-secondary">Origine</label>
            <select name="source" class="ui-input w-full">
                <option value="">Toutes</option>
                <option value="AUTOMATIQUE" @selected($source === 'AUTOMATIQUE')>Automatique (lease)</option>
                <option value="MANUEL" @selected($source === 'MANUEL')>Manuel</option>
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Type de coupure</label>
            <select name="direction" class="ui-input w-full">
                @foreach($availableDirections as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['direction'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Statut</label>
            <select name="status" class="ui-input w-full">
                @foreach($availableStatuses as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-secondary">Période</label>
            <select name="period" class="ui-input w-full" id="unifiedPeriodSelect">
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
            <input type="date" name="specific_date" value="{{ $filters['specific_date'] ?? '' }}" class="ui-input w-full">
        </div>

        <div id="unifiedRangeFromWrap" class="{{ $period === 'range' ? '' : 'hidden' }}">
            <label class="text-xs text-secondary">Du</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="ui-input w-full">
        </div>

        <div id="unifiedRangeToWrap" class="{{ $period === 'range' ? '' : 'hidden' }}">
            <label class="text-xs text-secondary">Au</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="ui-input w-full">
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
                        <td colspan="8" class="p-0">
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
                        <td colspan="8" class="text-center text-secondary py-6">Aucun événement pour cette période.</td>
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
    const periodSelect = document.getElementById('unifiedPeriodSelect');
    const specificWrap  = document.getElementById('unifiedSpecificDateWrap');
    const fromWrap      = document.getElementById('unifiedRangeFromWrap');
    const toWrap        = document.getElementById('unifiedRangeToWrap');

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
})();
</script>
@endsection
