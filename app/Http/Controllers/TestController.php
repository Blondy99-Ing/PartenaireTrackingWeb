<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function profile()
    {
        return view('users.profile');
    }


     public function dashboard()
    {
        return view('dashboards.index');
    }

     

     public function alert()
    {
        return view('alerts.index');
    }


      public function alertcentre()
    {
        return view('alerts.alert');
    }
}
