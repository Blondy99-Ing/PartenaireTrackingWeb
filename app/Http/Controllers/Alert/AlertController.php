<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;
use App\Models\Voiture;

class AlertController extends Controller
{
    /**
     * Display a listing of geofence alerts.
     */
    public function index()
    {
        // Load alerts with vehicles, latest location, and associated user
        $alerts = Alert::with(['voiture.latestLocation', 'voiture.user'])->latest()->get();
        $unreadCount = Alert::where('read', false)->count();

        return view('alerts.index', compact('alerts', 'unreadCount'));
    }


    /**
     * Simulate turning off a car (placeholder).
     */
    public function turnOff(Voiture $voiture)
    {
        // Here you could send a shutdown command, or update a "status" field
        // For now, simulate it with a flash message
        return redirect()->back()->with('success', "Vehicle {$voiture->immatriculation} has been turned off (simulated).");
    }

    public function markAsRead(Alert $alert)
    {
        $alert->update([
            'read' => true,
        ]);

        return redirect()->back()->with('success', 'Alert marked as read.');
    }

    public function markAsUnread(Alert $alert)
    {
        $alert->update([
            'read' => false,
        ]);

        return redirect()->back()->with('success', 'Alert marked as unread.');
    }
}
