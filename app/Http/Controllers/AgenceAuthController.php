<?php



namespace App\Http\Controllers;

use App\Models\Agence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgenceAuthController extends Controller
{
    /**
     * Authentifier l'agence avec son `agency_unique_id` et mot de passe.
     */
    public function authenticate(Request $request)
    {
        // Validation des entrées
        $validated = $request->validate([
            'agence_unique_id' => 'required|string|exists:agences,agence_unique_id', // L'ID unique de l'agence
            'password' => 'required|string',  // Le mot de passe de l'agence
        ]);

        // Recherche de l'agence par son `agency_unique_id`
        $agence = Agence::where('agence_unique_id', $request->agence_unique_id)->first();

        // Vérification du mot de passe
        if ($agence && Hash::check($request->password, $agence->password)) {
            // Authentification réussie, stocker l'agence dans la session
            session(['agence' => $agence]);  // Sauvegarder l'agence dans la session

            // Générer un jeton d'authentification
            $token = \Str::random(60);  // Exemple de jeton unique
            session(['auth_token' => $token]);  // Sauvegarder le jeton dans la session

            // Rediriger vers la page d'accueil
            return redirect()->route('dashboard');  // Vous pouvez changer la redirection
        }

        // Si les identifiants sont incorrects
        return response()->json(['message' => 'Identifiants incorrects'], 401);
    }

     /**
     * Déconnexion de l'agence.
     */
    public function logout()
    {
        // Supprimer les informations de session de l'agence
        session()->forget('agence');  // Effacer l'agence de la session
        session()->forget('auth_token');  // Effacer le token d'authentification

        // Optionnellement, vous pouvez aussi supprimer toutes les données de session
        // session()->flush();

        // Rediriger vers la page de connexion
        return redirect()->route('login');
    }
}
