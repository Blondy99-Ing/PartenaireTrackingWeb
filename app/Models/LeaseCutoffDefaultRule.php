<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseCutoffDefaultRule extends Model
{
    protected $table = 'lease_cutoff_default_rules';

    protected $fillable = [
        'partner_id',
        'type_contrat_id',
        'type_contrat_label',
        'type_contrat_code',
        'is_enabled',
        'cutoff_time',
        'timezone',
        'grace_days',
        'active_days',
        'only_when_stopped',
        'notify_before_cutoff',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'grace_days' => 'integer',
        'active_days' => 'array',
        'only_when_stopped' => 'boolean',
        'notify_before_cutoff' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActiveOnDay(string $day): bool
    {
        return in_array(strtolower($day), $this->active_days ?? [], true);
    }
}