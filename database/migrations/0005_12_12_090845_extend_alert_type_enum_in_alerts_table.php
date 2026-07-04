<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // On récupère le type actuel de la colonne (ex: enum('geofence','safe_zone'))
        $column = DB::selectOne("
            SELECT COLUMN_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'alerts'
              AND COLUMN_NAME = 'alert_type'
        ");

        if (!$column) {
            // Sécurité : si la colonne n'existe pas, on ne fait rien
            return;
        }

        $type = $column->COLUMN_TYPE; // ex: "enum('geofence','safe_zone')"

        // On enlève "enum(" et ")" pour récupérer juste "'a','b','c'"
        $inside = trim($type, "enum()");

        // On explose en valeurs en gérant les quotes
        $currentValues = array_map(function ($v) {
            return trim($v, " '\"");
        }, explode(',', $inside));

        // Nouveaux types à ajouter
        $toAdd = ['time_zone', 'stolen', 'low_battery'];

        foreach ($toAdd as $value) {
            if (!in_array($value, $currentValues, true)) {
                $currentValues[] = $value;
            }
        }

        // On reconstruit la définition ENUM('v1','v2',...)
        $enumList = implode("','", $currentValues);
        $enumSql  = "ENUM('{$enumList}')";

        // ⚠️ Adapter NULL / NOT NULL et DEFAULT si nécessaire
        DB::statement("
            ALTER TABLE `alerts`
            MODIFY `alert_type` {$enumSql} NULL
        ");
    }

    public function down(): void
    {
        // Option simple : on ne retire pas les valeurs
        // (évite de casser si des lignes utilisent ces valeurs)
        //
        // Si tu veux les retirer proprement, on peut faire le même genre
        // de logique mais en enlevant 'time_zone','stolen','low_battery'.
    }
};
