<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employe; 
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Storage; // IMPORTANT : Assurez-vous d'importer Storage

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        // Retourne la vue d'inscription
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.Employe::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('employes', 'public');
        }

        $employe = Employe::create([
            'unique_id' => (string) Str::uuid(),
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'ville' => $request->ville,
            'quartier' => $request->quartier,
            'photo' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employé enregistré avec succès !',
            'employe' => $employe
        ]);
    }
}