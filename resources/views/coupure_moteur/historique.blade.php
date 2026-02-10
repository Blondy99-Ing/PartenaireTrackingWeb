@extends('layouts.app')

@section('title', 'Historique Coupure / Allumage')

@section('content')
<div class="space-y-4 p-0 md:p-4">

@php
  $authUser = auth('web')->user();
@endphp

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
     style="border-color: var(--color-border-subtle);">
  <div class="flex mt-4 sm:mt-0 space-x-4">
    <a href="{{ route('engine.action.index') }}"
       class="py-2 px-4 rounded-lg font-semibold transition-colors text-secondary hover:text-primary">
      <i class="fas fa-power-off mr-2"></i> Actions
    </a>

    <a href="{{ route('engine.action.history') }}"
       class="py-2 px-4 rounded-lg font-semibold transition-colors
        {{ request()->routeIs('engine.action.history') ? 'text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary' }}">
      <i class="fas fa-history mr-2"></i> Historique
    </a>
  </div>
</div>

<div class="ui-card mt-2 p-4">
  <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div>
      <label class="text-xs text-secondary">Véhicule</label>
      <select name="vehicule_id" class="ui-input w-full">
        <option value="">Tous</option>
        @foreach($voitures as $v)
          <option value="{{ $v->id }}" @selected((string)request('vehicule_id') === (string)$v->id)>
            {{ $v->immatriculation }}
          </option>
        @endforeach
      </select>
    </div>

    <div>
      <label class="text-xs text-secondary">Type</label>
      <select name="type" class="ui-input w-full">
        <option value="">Tous</option>
        <option value="COUPURE" @selected(request('type') === 'COUPURE')>COUPURE</option>
        <option value="ALLUMAGE" @selected(request('type') === 'ALLUMAGE')>ALLUMAGE</option>
      </select>
    </div>

    <div class="flex items-end gap-2">
      <button class="btn-primary" type="submit">
        <i class="fas fa-filter mr-2"></i> Filtrer
      </button>
      <a class="btn-secondary" href="{{ route('engine.action.history') }}">Reset</a>
    </div>
  </form>
</div>

<div class="ui-card mt-2">
  <h2 class="text-xl font-bold font-orbitron mb-6">Historique des commandes</h2>

  <div class="ui-table-container shadow-md">
    <table class="ui-table w-full">
      <thead>
        <tr>
          <th>Date</th>
          <th>Véhicule</th>
          <th>Chauffeur</th>
          <th>Type</th>
          <th>Status</th>
          <th>CmdNo</th>
        </tr>
      </thead>
      <tbody>
        @forelse($commandes as $cmd)
          @php
            $veh = $cmd->vehicule;
            $chauffeur = $veh?->chauffeurActuelPartner?->chauffeur;
          @endphp
          <tr>
            <td class="text-sm">{{ optional($cmd->created_at)->format('d/m/Y H:i') }}</td>

            <td>
              <div class="flex flex-col leading-tight">
                <span class="font-semibold">{{ $veh?->immatriculation ?? '—' }}</span>
                <span class="text-xs text-secondary">{{ trim(($veh?->marque ?? '').' '.($veh?->model ?? '')) }}</span>
              </div>
            </td>

            <td>
              @if($chauffeur)
                <div class="flex flex-col leading-tight">
                  <span class="font-semibold">{{ $chauffeur->prenom }} {{ $chauffeur->nom }}</span>
                  <span class="text-xs text-secondary">{{ $chauffeur->phone }}</span>
                </div>
              @else
                <span class="text-xs text-secondary">—</span>
              @endif
            </td>

            <td>
              <span class="font-semibold {{ $cmd->type_commande === 'COUPURE' ? 'text-red-600' : 'text-green-600' }}">
                {{ $cmd->type_commande ?? '—' }}
              </span>
            </td>

            <td>
              <span class="text-sm">{{ $cmd->status ?? '—' }}</span>
            </td>

            <td class="font-mono text-xs">{{ $cmd->CmdNo }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-secondary py-6">Aucune commande</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">
    {{ $commandes->links() }}
  </div>
</div>

</div>
@endsection
