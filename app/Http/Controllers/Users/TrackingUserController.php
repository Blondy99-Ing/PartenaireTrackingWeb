<?php
namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User; // Modèle pour les utilisateur 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;  // Gardez cet import ici
use Illuminate\Support\Str;        // Déplacez cet import ici

class TrackingUserController extends Controller
{
     /**
     * Affiche la vue des utilisateurs swap avec la liste des agents et le formulaire d'ajout.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
      
        // Récupérer les utilisateurs
        $users = User::all();

        // Retourner la vue avec les agents et les informations de l'agence
        return view('users.tracking_users', compact('users')); 
    }

    /**
     * Enregistrer un nouvel agent swap.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validation des données du formulaire
        $validatedData = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users', // Validation de l'email
            'ville' => 'required|string|max:255',
            'quartier' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'password' => 'required|string|min:6|confirmed',
        ]);
    
        // Gérer l'upload de la photo (si présente)
        if ($request->hasFile('photo')) {
            // Upload de la photo et stockage
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }
    
        // Générer le user_unique_id avec le format souhaité
        $anneemois = now()->format('Ym'); // Année et mois actuel, format "202501"
        $validatedData['user_unique_id'] = 'PxT-' . $anneemois . '-' . Str::random(4); // Génération de l'ID
    
    
        // Créer l'utilisateur dans la base de données
        User::create($validatedData);
    
        // Rediriger avec un message de succès
        return redirect()->route('tracking.users')->with('success', 'Utilisateur ajouté avec succès.');
    }


    /**
 * Affiche le formulaire pour modifier un utilisateur existant.
 *
 * @param \App\Models\User $trackingUser
 * @return \Illuminate\View\View
 */
public function edit(User $trackingUser)
{
    return view('users.edit_user', compact('trackingUser'));
}


/**
 * Met à jour les informations d'un utilisateur existant.
 *
 * @param \Illuminate\Http\Request $request
 * @param \App\Models\User $trackingUser
 * @return \Illuminate\Http\RedirectResponse
 */
public function update(Request $request, User $trackingUser)
{
    // Validation des données
    $validatedData = $request->validate([
        'nom' => 'required|string|max:255',
        'prenom' => 'required|string|max:255',
        'phone' => 'required|string|max:255|unique:users,phone,' . $trackingUser->id,
        'email' => 'required|email|unique:users,email,' . $trackingUser->id,
        'ville' => 'required|string|max:255',
        'quartier' => 'required|string|max:255',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
    ]);

    // Gérer l'upload de la nouvelle photo (si présente)
    if ($request->hasFile('photo')) {
        $validatedData['photo'] = $request->file('photo')->store('photos');
    }

    // Mettre à jour les informations de l'utilisateur
    $trackingUser->update($validatedData);

    // Rediriger avec un message de succès
    return redirect()->route('tracking.users')->with('success', 'Utilisateur mis à jour avec succès.');
}

    

    /**
     * Supprime un utilisateur.
     *
     * @param \App\Models\SwapUser $swapUser
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Users $trackingUser)
    {
        // Supprimer l'agent swap
        $trackingUser->delete();

        return redirect()->route('tracking.users.index')->with('success', 'Utilisateur supprimé avec succès.');
    }
}
