<?php

namespace App\Models;

use App\Enums\PartnerPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'key',
        'label',
        'group',
        'description',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * Staff members who have been granted this permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    /**
     * Matching enum case (single source of truth for label/group/sensitivity).
     */
    public function toEnum(): ?PartnerPermission
    {
        return PartnerPermission::tryFrom($this->key);
    }
}
