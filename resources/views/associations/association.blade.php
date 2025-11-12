@extends('layouts.app')

@section('title', 'Associations des véhicules aux utilisateurs')

@section('content')
<div class=" m-5">

    <!-- Messages de succès ou d'erreur -->
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Formulaire pour les associations -->
    <div class="row">
        <!-- Liste des Utilisateurs -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Liste des Utilisateurs</div>
                <div class="card-body">
                    <input type="text" id="userSearch" class="form-control mb-3"
                        placeholder="Rechercher un utilisateur">
                    <div id="userList" style="height: 200px; overflow-y: auto;">
                        @foreach($users as $user)
                        <div class="form-check">
                            <input type="radio" name="user_unique_id" value="{{ $user->user_unique_id }}"
                                id="user_{{ $user->id }}" class="form-check-input">
                            <label for="user_{{ $user->id }}" class="form-check-label">
                                {{ $user->nom }} {{ $user->prenom }} (ID: {{ $user->user_unique_id }})
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des Véhicules -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Liste des Véhicules</div>
                <div class="card-body">
                    <input type="text" id="voitureSearch" class="form-control mb-3"
                        placeholder="Rechercher un véhicule">
                    <div id="voitureList" style="height: 200px; overflow-y: auto;">
                        @foreach($voitures as $voiture)
                        <div class="form-check">
                            <input type="checkbox" name="voiture_unique_id[]" value="{{ $voiture->voiture_unique_id }}"
                                id="voiture_{{ $voiture->id }}" class="form-check-input">
                            <label for="voiture_{{ $voiture->id }}" class="form-check-label">
                                Immatriculation: {{ $voiture->immatriculation }} - Marque: {{ $voiture->marque }}
                                (ID: {{ $voiture->voiture_unique_id }})
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton Associer -->
    <div class="row mt-3">
        <div class="col-md-12 text-center">
            <form action="{{ route('association.store') }}" method="POST">
                @csrf
                <input type="hidden" id="selectedUser" name="user_unique_id">
                <input type="hidden" id="selectedVoitures" name="voiture_unique_id">
                <button type="submit" class="btn btn-primary">Associer</button>
            </form>
        </div>
    </div>

    <!-- Tableau des Associations -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Liste des Associations Utilisateur-Véhicule</div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID Utilisateur</th>
                                <th>Nom et Prénom Utilisateur</th>
                                <th>ID Véhicule</th>
                                <th>Immatriculation</th>
                                <th>Marque</th>
                                <th>Date d'Association</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($associations as $voiture)
                            <tr>
                                <td>{{ $voiture->utilisateur->first()->user_unique_id ?? 'Non défini' }}</td>
                                <td>{{ $voiture->utilisateur->first()->nom ?? 'N/A' }}
                                    {{ $voiture->utilisateur->first()->prenom ?? 'N/A' }}</td>
                                <td>{{ $voiture->voiture_unique_id }}</td>
                                <td>{{ $voiture->immatriculation }}</td>
                                <td>{{ $voiture->marque }}</td>
                                <td>{{ $voiture->created_at }}</td>
                            </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// Recherche dynamique pour les utilisateurs
document.getElementById('userSearch').addEventListener('input', function() {
    let query = this.value.toLowerCase();
    document.querySelectorAll('#userList .form-check').forEach(function(item) {
        item.style.display = item.innerText.toLowerCase().includes(query) ? 'block' : 'none';
    });
});

// Recherche dynamique pour les voitures
document.getElementById('voitureSearch').addEventListener('input', function() {
    let query = this.value.toLowerCase();
    document.querySelectorAll('#voitureList .form-check').forEach(function(item) {
        item.style.display = item.innerText.toLowerCase().includes(query) ? 'block' : 'none';
    });
});

// Gestion des sélections
document.querySelectorAll('input[name="user_unique_id"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.getElementById('selectedUser').value = this.value;
    });
});

document.querySelectorAll('input[name="voiture_unique_id[]"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        let selected = [];
        document.querySelectorAll('input[name="voiture_unique_id[]"]:checked').forEach(function(
            checkedBox) {
            selected.push(checkedBox.value);
        });
        document.getElementById('selectedVoitures').value = selected.join(',');
    });
});
</script>
@endsection