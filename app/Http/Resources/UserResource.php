<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // ✅ roles (بدون ما نعمل query إذا مش محمّلة)
        $roles = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->values()
            : $this->getRoleNames()->values(); // Spatie returns a collection

        $permissions = $this->roles
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        return [
            'id' => (string) $this->id,
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'username' => $this->username,

            // ✅ role column (مصدر سريع للتقسيم)
            'role' => $this->role,

            // ✅ spatie roles + permissions
            'roles' => $roles,
            'permissions' => $permissions,

            'orders_count' => $this->when(isset($this->orders_count), $this->orders_count),
            'cards_count'  => $this->when(isset($this->cards_count), $this->cards_count),

            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
