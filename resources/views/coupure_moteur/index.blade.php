{{-- resources/views/coupure_moteur/partner.blade.php --}}
@extends('layouts.app')

@section('title', 'Immobilisation des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Header simple --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
         style="border-color: var(--color-border-subtle);">
        <div>
            <h1 class="text-2xl font-bold font-orbitron">Immobilisation des Véhicules</h1>
            <p class="text-sm text-secondary mt-1">
                Couper / Rétablir le moteur via GPS (18GPS). Le statut se met à jour automatiquement.
            </p>
        </div>
    </div>

    {{-- Liste des véhicules --}}
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>

        <div class="ui-table-container shadow-md">
            <table id="myTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>Chauffeur</th>
                        <th>GPS</th>
                        <th>Moteur</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                        @php
                            $chauffeur = $voiture->chauffeurActuelPartner?->chauffeur;
                            $chauffeurName = $chauffeur
                                ? trim(($chauffeur->nom ?? '') . ' ' . ($chauffeur->prenom ?? ''))
                                : null;
                        @endphp
                        <tr>
                            <td class="font-semibold">{{ $voiture->immatriculation }}</td>
                            <td>{{ $voiture->model }}</td>
                            <td>{{ $voiture->marque }}</td>
                            <td>
                                <div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div>
                            </td>

                            <td class="text-sm">
                                @if($chauffeur)
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full overflow-hidden bg-gray-200 dark:bg-white/10 flex items-center justify-center">
                                            @if(!empty($chauffeur->photo_url))
                                                <img src="{{ $chauffeur->photo_url }}" alt="Photo chauffeur" class="w-full h-full object-cover">
                                            @else
                                                <i class="fas fa-user text-secondary"></i>
                                            @endif
                                        </div>
                                        <div class="leading-tight">
                                            <div class="font-semibold">{{ $chauffeurName ?: 'Chauffeur' }}</div>
                                            <div class="text-xs text-secondary">{{ $chauffeur->phone ?? '' }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-secondary">Non assigné</span>
                                @endif
                            </td>

                            <td class="font-mono text-xs">{{ $voiture->mac_id_gps }}</td>

                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    {{-- Toggle --}}
                                    <button
                                        class="engine-toggle"
                                        data-id="{{ $voiture->id }}"
                                        data-cut="0"
                                        data-toggle-url="{{ route('voitures.toggleEngine', $voiture->id, false) }}"
                                        data-status-url="{{ route('voitures.engineStatus', $voiture->id, false) }}"
                                        data-immat="{{ $voiture->immatriculation }}"
                                        data-marque="{{ $voiture->marque }}"
                                        data-chauffeur="{{ $chauffeurName ?: '' }}"
                                        aria-label="Toggle moteur"
                                        type="button"
                                    >
                                        <span class="engine-knob">
                                            <i class="fas fa-power-off"></i>
                                        </span>
                                    </button>

                                    <div class="flex flex-col leading-tight">
                                        <span class="engine-badge" id="engineBadge-{{ $voiture->id }}">Chargement…</span>
                                        <span class="gps-badge" id="gpsBadge-{{ $voiture->id }}">GPS…</span>
                                    </div>
                                </div>
                            </td>

                        </tr>
                    @endforeach

                    @if(empty($voitures) || count($voitures) === 0)
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-6">
                                Aucun véhicule trouvé.
                            </td>
                        </tr>
                    @endif
                </tbody>

            </table>
        </div>
    </div>

</div>

{{-- ===================== MODALE CONFIRMATION ===================== --}}
<div id="engineConfirmModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black bg-opacity-50">
    <div class="bg-card rounded-2xl shadow-2xl w-full max-w-xl p-6 relative ui-card">

        <button type="button" id="closeEngineModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Confirmation
        </h2>

        <p class="text-sm text-secondary mb-2">
            <span class="font-medium">Véhicule :</span>
            <span id="engineModalVehicleLabel" class="font-semibold text-primary"></span>
        </p>

        <p class="text-sm text-secondary mb-2">
            <span class="font-medium">Chauffeur :</span>
            <span id="engineModalDriverLabel" class="font-semibold"></span>
        </p>

        <div class="ui-card p-4 mt-3" style="padding: 1rem;">
            <p class="text-sm" style="color: var(--color-text);">
                <span id="engineModalActionTxt" class="font-semibold"></span>
            </p>
            <p class="text-xs text-secondary mt-2">
                Cette action enverra une commande au GPS. Le statut sera actualisé automatiquement.
            </p>
        </div>

        <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-border-subtle">
            <button type="button" id="cancelEngineBtn" class="btn-secondary">
                Annuler
            </button>
            <button type="button" id="confirmEngineBtn" class="btn-primary">
                <i class="fas fa-check mr-2"></i>
                Confirmer
            </button>
        </div>

    </div>
</div>

<style>
/* ===== Toggle style ===== */
.engine-toggle{
    width: 74px;
    height: 36px;
    border-radius: 999px;
    position: relative;

    background: rgba(0,0,0,.06);
    border: 1px solid rgba(0,0,0,.08);
    box-shadow: 0 10px 26px rgba(0,0,0,.10);

    transition: .2s ease;
    overflow: hidden;
}
.dark-mode .engine-toggle{
    background: rgba(255,255,255,.08);
    border-color: rgba(255,255,255,.10);
}

/* knob */
.engine-toggle .engine-knob{
    position: absolute;
    top: 4px;
    left: 4px;
    width: 28px;
    height: 28px;
    border-radius: 999px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: var(--color-card);
    color: var(--color-text);
    box-shadow: 0 10px 22px rgba(0,0,0,.18);

    transition: .22s ease;
    font-size: 13px;
}

/* ON */
.engine-toggle.is-on{
    background: rgba(34,197,94,.20);
    border-color: rgba(34,197,94,.35);
}
.engine-toggle.is-on .engine-knob{
    left: 42px;
    color: #16a34a;
}

/* CUT */
.engine-toggle.is-cut{
    background: rgba(239,68,68,.18);
    border-color: rgba(239,68,68,.35);
}
.engine-toggle.is-cut .engine-knob{
    left: 4px;
    color: #b91c1c;
}

/* loading */
.engine-toggle.is-loading{
    opacity: .75;
    pointer-events: none;
    filter: grayscale(.15);
}

/* badges */
.engine-badge{
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    font-weight: 800;
    padding: 2px 10px;
    border-radius: 999px;
    width: fit-content;
    background: #f3f4f6;
    color: #111827;
}
.dark-mode .engine-badge{
    background: rgba(255,255,255,.08);
    color: var(--color-text);
}
.engine-badge.cut{ background: rgba(239,68,68,.16); color: #b91c1c; }
.engine-badge.on{ background: rgba(34,197,94,.16); color: #166534; }
.engine-badge.pending{ background: rgba(245,130,32,.18); color: var(--color-primary-dark); }

.gps-badge{
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 999px;
    width: fit-content;
    background: rgba(99,102,241,.12);
    color: #3730a3;
}
.dark-mode .gps-badge{
    background: rgba(99,102,241,.18);
    color: #c7d2fe;
}
.gps-badge.off{ background: rgba(107,114,128,.14); color: #374151; }
</style>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const modal = document.getElementById('engineConfirmModal');
  const closeBtn = document.getElementById('closeEngineModalBtn');
  const cancelBtn = document.getElementById('cancelEngineBtn');
  const confirmBtn = document.getElementById('confirmEngineBtn');

  const vehicleLabel = document.getElementById('engineModalVehicleLabel');
  const driverLabel = document.getElementById('engineModalDriverLabel');
  const actionTxt = document.getElementById('engineModalActionTxt');

  const switches = Array.from(document.querySelectorAll('.engine-toggle'));
  const ids = switches.map(b => b.dataset.id).filter(Boolean);

  let pendingTarget = null;
  let pendingAction = null; // 'cut' | 'restore'
  let pendingExpectedCut = null;

  const openModal = () => {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  };
  const closeModal = () => {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pendingTarget = null;
    pendingAction = null;
    pendingExpectedCut = null;
  };

  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  // ===== Toast (si ton layout contient #toast-container) =====
  const pushToast = (type, title, msg) => {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'} pointer-events-auto`;
    toast.setAttribute('role', 'alert');

    toast.innerHTML = `
      <div class="toast-icon">
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
      </div>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        <div class="toast-msg">${msg}</div>
      </div>
      <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
    `;

    container.prepend(toast);
    requestAnimationFrame(() => toast.classList.add('show'));

    const close = () => {
      toast.classList.remove('show');
      toast.classList.add('hide');
      setTimeout(() => toast.remove(), 260);
    };

    toast.querySelector('.toast-close')?.addEventListener('click', close);
    setTimeout(close, 5000);
  };

  const fetchJson = async (url, opt = {}, timeoutMs = 12000) => {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
      const res = await fetch(url, { ...opt, signal: ctrl.signal });
      const json = await res.json().catch(() => null);
      return { ok: res.ok, status: res.status, json };
    } finally {
      clearTimeout(t);
    }
  };

  const setUI = (id, payload) => {
    const btn = document.querySelector(`.engine-toggle[data-id="${id}"]`);
    const engineBadge = document.getElementById(`engineBadge-${id}`);
    const gpsBadge = document.getElementById(`gpsBadge-${id}`);
    if (!btn || !engineBadge || !gpsBadge) return;

    if (!payload || payload.success === false) {
      btn.classList.remove('is-on', 'is-cut', 'is-loading');
      engineBadge.textContent = 'UNKNOWN';
      engineBadge.className = 'engine-badge';
      gpsBadge.textContent = 'GPS: N/A';
      gpsBadge.className = 'gps-badge off';
      return;
    }

    const cut = !!payload.engine?.cut;
    const online = payload.gps?.online;

    btn.dataset.cut = cut ? '1' : '0';
    btn.classList.toggle('is-cut', cut);
    btn.classList.toggle('is-on', !cut);

    engineBadge.textContent = cut ? 'COUPÉ' : 'ACTIF';
    engineBadge.className = 'engine-badge ' + (cut ? 'cut' : 'on');

    let gpsTxt = 'GPS: N/A';
    if (online === true) gpsTxt = 'GPS: ONLINE';
    if (online === false) gpsTxt = 'GPS: OFFLINE';

    gpsBadge.textContent = gpsTxt;
    gpsBadge.className = 'gps-badge ' + (online === true ? '' : 'off');

    btn.title = cut ? 'Rétablir le moteur' : 'Couper le moteur';
  };

  const setPending = (id, txt) => {
    const btn = document.querySelector(`.engine-toggle[data-id="${id}"]`);
    const engineBadge = document.getElementById(`engineBadge-${id}`);
    if (!btn || !engineBadge) return;

    btn.classList.add('is-loading');
    engineBadge.textContent = txt || 'Commande en cours…';
    engineBadge.className = 'engine-badge pending';
  };

  // Poll confirmation (rapide)
  const pollConfirm = async (statusUrl, expectedCut, tries = 10, intervalMs = 900) => {
    for (let i = 0; i < tries; i++) {
      await new Promise(r => setTimeout(r, intervalMs));

      const r = await fetchJson(`${statusUrl}?_t=${Date.now()}`, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }, 12000);

      if (!r.ok || !r.json || !r.json.success) continue;

      const gotCut = !!r.json.engine?.cut;
      if (gotCut === expectedCut) {
        return { confirmed: true, json: r.json };
      }
    }
    return { confirmed: false, json: null };
  };

  // ===================== BATCH LOAD =====================
  const batchUrl = @json(route('voitures.engineStatusBatch', [], false));

  fetchJson(`${batchUrl}?ids=${encodeURIComponent(ids.join(','))}&_t=${Date.now()}`, {
    cache: 'no-store',
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  }, 15000)
  .then(({ok, json}) => {
    if (!ok || !json) throw new Error('batch failed');
    ids.forEach(id => setUI(id, json.data?.[id] ?? { success:false }));
  })
  .catch(() => ids.forEach(id => setUI(id, { success:false })));

  // ===================== OPEN CONFIRM MODAL =====================
  switches.forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('is-loading')) return;

      const currentCut = (btn.dataset.cut === '1');
      pendingAction = currentCut ? 'restore' : 'cut';
      pendingExpectedCut = !currentCut;
      pendingTarget = btn;

      const marque = btn.dataset.marque || '';
      const immat = btn.dataset.immat || '';
      const chauffeur = btn.dataset.chauffeur || '';

      vehicleLabel.textContent = `${marque} • ${immat}`.trim();
      driverLabel.textContent = chauffeur ? chauffeur : 'Non assigné';

      actionTxt.textContent = pendingAction === 'cut'
        ? `Voulez-vous vraiment COUPER le moteur de ce véhicule ?`
        : `Voulez-vous vraiment RÉTABLIR le moteur de ce véhicule ?`;

      confirmBtn.innerHTML = pendingAction === 'cut'
        ? `<i class="fas fa-power-off mr-2"></i> Couper`
        : `<i class="fas fa-rotate-right mr-2"></i> Allumer`;

      openModal();
    });
  });

  // ===================== CONFIRM ACTION =====================
  confirmBtn?.addEventListener('click', async () => {
    if (!pendingTarget) return;

    const btn = pendingTarget;
    const id = btn.dataset.id;
    const toggleUrl = btn.dataset.toggleUrl;
    const statusUrl = btn.dataset.statusUrl;
    const expectedCut = !!pendingExpectedCut;

    closeModal();
    setPending(id, expectedCut ? 'Coupure en cours…' : 'Allumage en cours…');

    try {
      const res = await fetch(toggleUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ action: pendingAction })
      });

      if (res.status === 419) {
        pushToast('error', 'Session expirée', 'Recharge la page (CSRF).');
        window.location.reload();
        return;
      }

      const data = await res.json().catch(() => null);
      const ok = res.ok && data && data.success;

      if (!ok) {
        pushToast('error', 'Erreur', (data?.message || data?.return_msg || 'Échec commande moteur'));
        setUI(id, { success:false });
        return;
      }

      // ✅ UI instant
      setUI(id, { success:true, engine:{ cut: expectedCut }, gps:{ online:null } });

      pushToast(
        'success',
        'Succès',
        (data.message || 'Commande envoyée') + (data.cmd_no ? ` • CmdNo: ${data.cmd_no}` : '')
      );

      // ✅ confirmation rapide
      const p = await pollConfirm(statusUrl, expectedCut, 10, 900);
      if (p.confirmed && p.json) {
        setUI(id, p.json);
        pushToast('success', 'Confirmé', expectedCut ? 'Moteur coupé (confirmé).' : 'Moteur rétabli (confirmé).');
      } else {
        const r = await fetchJson(`${statusUrl}?_t=${Date.now()}`, {
          cache: 'no-store',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }, 15000);

        if (r.ok && r.json && r.json.success) setUI(id, r.json);

        pushToast('error', 'Info', "Commande envoyée, mais le GPS n'a pas encore remonté le statut.");
      }

    } catch (e) {
      pushToast('error', 'Erreur réseau', 'Impossible de contacter le serveur.');
    } finally {
      const b = document.querySelector(`.engine-toggle[data-id="${id}"]`);
      b?.classList.remove('is-loading');
    }
  });
});
</script>
@endpush
