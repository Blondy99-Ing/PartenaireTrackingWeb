<?php

namespace App\Http\Resources\V1\Partner;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_unique_id' => $this->user_unique_id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'phone' => $this->phone,
            'email' => $this->email,
            'ville' => $this->ville,
            'quartier' => $this->quartier,
            'photo' => $this->photo,
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role?->id,
                'slug' => $this->role?->slug,
                'name' => $this->role?->name,
            ]),
            'partner_id' => $this->partner_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
