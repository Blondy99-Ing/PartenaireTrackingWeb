<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Ville extends Model
{
    protected $fillable = ['code_ville', 'name', 'geom'];

    /* =========================
     * ✅ NORMALISATION SET + GET
     * ========================= */

    protected function codeVille(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->upperOrNull($value),
            set: fn ($value) => $this->upperOrNull($value),
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    /* =========================
     * Helpers unicode-safe
     * ========================= */

    private function upperOrNull($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : mb_strtoupper($v, 'UTF-8');
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