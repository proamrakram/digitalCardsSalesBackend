<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
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
            'uuid' => $this->uuid,
            // 'category_id' => (string) $this->category_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => (string) $this->price,
            'status' => $this->status,
            'type' => $this->type,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'cards' => CardResource::collection($this->whenLoaded('cards')),
            'cards_counts' => [
                'total' => $this->cards()->count(),
                'available' => $this->cards()->where('status', 'available')->count(),
                'reserved' => $this->cards()->where('status', 'reserved')->count(),
                'sold' => $this->cards()->where('status', 'sold')->count(),
            ],
        ];
    }
}
