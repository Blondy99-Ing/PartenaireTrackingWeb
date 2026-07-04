<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Voiture;
use App\Models\Alert;
use Carbon\Carbon;
use App\Services\WhatsAppService;

class CheckGeofenceAlerts extends Command
{
    protected $signature = 'alerts:check-geofence';
    protected $description = 'Check each vehicle\'s location against its polygon geofence and generate alerts if needed';

    protected $whatsApp;

    public function __construct(WhatsAppService $whatsApp)
    {
        parent::__construct();
        $this->whatsApp = $whatsApp;
    }

    public function handle()
    {
        $voitures = Voiture::with('latestLocation', 'user')->get(); // Load vehicles + latest location + users

        foreach ($voitures as $voiture) {
            $location = $voiture->latestLocation;

            if (!$location) {
                $this->warn("No latest location found for vehicle {$voiture->immatriculation}");
                continue;
            }

            if (is_null($voiture->region_polygon)) {
                $this->warn("No region polygon for vehicle {$voiture->immatriculation}");
                continue;
            }

            $polygon = json_decode($voiture->region_polygon, true);

            if (!isset($polygon['coordinates'][0])) {
                $this->warn("Invalid region polygon data for vehicle {$voiture->immatriculation}");
                continue;
            }

            $coordinates = $polygon['coordinates'][0];

            $latitude = $location->latitude;
            $longitude = $location->longitude;

            if (!$this->pointInPolygon($latitude, $longitude, $coordinates)) {
                // Vehicle is OUTSIDE the polygon
                $alreadyAlerted = Alert::where('voiture_id', $voiture->id)
                    ->whereDate('alerted_at', Carbon::today())
                    ->exists();

                if (!$alreadyAlerted) {
                    $user = $voiture->user->first();

                    $locationName = $this->reverseGeocode($latitude, $longitude);

                    Alert::create([
                        'voiture_id' => $voiture->id,
                        'message' => "Vehicle exited geofence!",
                        'user_name' => $user ? ($user->prenom . ' ' . $user->nom) : null,
                        'user_phone' => $user ? $user->phone : null,
                        'immatriculation' => $voiture->immatriculation,
                        'location_name' => $locationName,
                        'alerted_at' => now(),
                        'sent' => false,
                    ]);

                    $this->info("🚨 Alert created for vehicle {$voiture->immatriculation}!");

                    if ($user && $user->phone) {
                        $this->whatsApp->sendMessage(
                            $user->phone,
                            "⚠️ Hello {$user->prenom}, your vehicle ({$voiture->immatriculation}) has exited the allowed region. Current location: {$locationName}. Please check immediately. - PROXYM GROUP"
                        );
                        $this->info("✅ WhatsApp sent to {$user->phone}");
                    } else {
                        $this->warn("No user or phone found for vehicle {$voiture->immatriculation}");
                    }
                }
            } else {
                $this->info("✅ Vehicle {$voiture->immatriculation} is inside the allowed region.");
            }
        }
    }

    private function pointInPolygon($lat, $lng, $polygon)
    {
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][1];
            $yi = $polygon[$i][0];
            $xj = $polygon[$j][1];
            $yj = $polygon[$j][0];

            $intersect = (($yi > $lng) != ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function reverseGeocode($lat, $lng)
    {
        try {
            $response = @file_get_contents("https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=10&addressdetails=1");
            if ($response) {
                $data = json_decode($response, true);
                return $data['display_name'] ?? "Unknown location";
            }
        } catch (\Exception $e) {
            return "Unknown location";
        }

        return "Unknown location";
    }
}
