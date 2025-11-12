<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
      

        // Passer la variable à la vue
        return view('layouts.app');
    }
}
