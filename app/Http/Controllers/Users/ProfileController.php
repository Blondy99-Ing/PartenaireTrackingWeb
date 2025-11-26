<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class ProfileController extends Controller
{
    public function show($id)
    {
        // Récupérer l'utilisateur avec ses véhicules associés
        $user = User::with(['voitures.latestLocation'])->findOrFail($id);

        // Compter le nombre de véhicules
        $vehiclesCount = $user->voitures->count();

        return view('users.profile', compact('user', 'vehiclesCount'));
    }
}
