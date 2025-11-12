<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateAgence
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
   
        public function handle(Request $request, Closure $next)
        {
            // Vérifier si l'agence est authentifiée
            if (!session()->has('agence') || !session()->has('auth_token')) {
                return redirect()->route('login');  // Rediriger vers la page de connexion si l'agence n'est pas authentifiée
            }
    
            // Si l'agence est authentifiée, continuer la requête
            return $next($request);
        }
    
}
