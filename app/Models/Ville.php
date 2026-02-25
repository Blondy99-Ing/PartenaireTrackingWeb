<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ville extends Model
{
    protected $fillable = ['code_ville', 'name', 'geom'];

    /* =========================
     * ✅ NORMALISATION AUTO (Mutators)
     * ========================= */

    public function setCodeVilleAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['code_ville'] = $v === '' ? null : mb_strtoupper($v, 'UTF-8');
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $this->titleWords($value);
    }

    private function titleWords($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;

        $v = preg_replace('/\s+/', ' ', $v);
        $v = mb_strtolower($v, 'UTF-8');

        return Str::title($v);
    }
}