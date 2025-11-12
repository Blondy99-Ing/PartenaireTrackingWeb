<?php

namespace App\Http\Controllers\Associations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User; // Importez le modèle User
use App\Models\Voiture; // Importez le modèle Voiture
use Illuminate\Support\Facades\DB; // Importez DB pour les requêtes SQL

class AssociationController extends Controller
{



    public function index()
    {
        // Récupérer les utilisateurs
        $users = User::all();
    
        // Récupérer les voitures non associées
        $voitures = Voiture::whereDoesntHave('utilisateur')->get();
    
        // Récupérer les associations existantes en utilisant les relations
        $associations = Voiture::with('utilisateur')->whereHas('utilisateur')->get();
    
        // Retourner la vue avec les données
        return view('associations.association', compact('users', 'voitures', 'associations'));
    }
    
    




    public function associerVoitureAUtilisateur(Request $request)
    {
        $request->validate([
            'user_unique_id' => 'required|exists:users,user_unique_id',
            'voiture_unique_id' => 'required', // Liste des voitures est obligatoire
        ]);
    
        // Récupérer l'utilisateur
        $user = User::where('user_unique_id', $request->user_unique_id)->first();
    
        // Récupérer les IDs des voitures sélectionnées
        $voitureIds = explode(',', $request->voiture_unique_id);
    
        foreach ($voitureIds as $voitureUniqueId) {
            $voiture = Voiture::where('voiture_unique_id', $voitureUniqueId)->first();
    
            // Vérifier si la voiture est déjà associée
            if ($voiture->utilisateur()->exists()) {
                return redirect()->back()->with('error', "La voiture {$voiture->immatriculation} est déjà associée.");
            }
    
            // Associer la voiture à l'utilisateur
            $voiture->utilisateur()->syncWithoutDetaching([$user->id]);
        }
    
        return redirect()->back()->with('success', 'Associations effectuées avec succès.');
    }

  
    public function destroy($id)
{
    DB::table('association_user_voiture')->where('id', $id)->delete();
    return redirect()->back()->with('success', 'Association supprimée avec succès.');
}

}
