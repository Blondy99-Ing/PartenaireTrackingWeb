@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Messages d'erreur ou succès --}}
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
        {{ session('error') }}
    </div>
    @endif
    @if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
        <strong class="font-bold">Erreurs de validation:</strong>
        <ul class="mt-1 list-disc list-inside">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif


    {{-- Liste des véhicules --}}
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>
        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>
                            <div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div>
                        </td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo"
                                class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td>
                            <button class="text-primary hover:text-orange-600 transition"
                                onclick="goToProfile({{ auth()->id() }}, {{ $voiture->id }})"
                                title="Localiser le véhicule">
                                <i class="fas fa-map-marker-alt text-xl"></i>
                            </button>
                        </td>


                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
#toggleEngineBtn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 16px;
    cursor: pointer;
}
</style>

<script>
function goToProfile(userId, vehicleId) {
    if (!userId || !vehicleId) return;
    window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
}
</script>


<script>
document.querySelectorAll(".toggleEngineBtn").forEach(btn => {
    const id = btn.dataset.id;

    // Fonction d'affichage
    function render(state) {
        btn.textContent = state ? "Éteindre" : "Allumer";
        btn.style.backgroundColor = state ? "red" : "green";
        btn.style.color = "white";
    }

    // Charger l'état réel au chargement
    fetch(`/voitures/${id}/engine-status`, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(res => res.json())
        .then(data => render(data.engine_on))
        .catch(() => render(false));

    // Toggle moteur au clic
    btn.addEventListener("click", () => {
        btn.textContent = "Traitement...";
        btn.style.opacity = 0.6;

        fetch(`/voitures/${id}/toggle-engine`, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(res => res.json())
            .then(data => {
                render(data.engine_on);
                btn.style.opacity = 1;
            })
            .catch(() => {
                alert("Erreur lors de la commande moteur");
            });
    });
});
</script>

@endsection