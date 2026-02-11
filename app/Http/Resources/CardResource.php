<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'uuid' => (string) $this->uuid,
            'package_id' => (string) $this->package_id,
            'reserved_by' => $this->user_id ? (string) $this->user_id : null,
            'status' => $this->status, // available, reserved, sold
            'reserved_at' => $this->reserved_at ? $this->reserved_at->format('Y-m-d H:i:s') : null,
            'sold_at' => $this->sold_at ? $this->sold_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'username' => $this->when($request->user()->hasRole('admin'), function () {
                return $this->username;
            }),
            'password' => $this->when($request->user()->hasRole('admin'), function () {
                return $this->password;
            }),
            'package' => new PackageResource($this->whenLoaded('package')),
        ];
    }
}
