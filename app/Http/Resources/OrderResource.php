<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'payment_method' => $this->payment_method,
            'payment_proof_url' => $this->payment_proof_url,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'price' => $this->price,
            'total_price' => (string) $this->total_price,
            'notes' => $this->notes,
            'status' => $this->status,
            'confirmed_at' => $this->confirmed_at ? $this->confirmed_at->format('Y-m-d H:i:s') : null,
            'cancelled_at' => $this->cancelled_at ? $this->cancelled_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),

            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'package' => $this->whenLoaded('package', fn() => new PackageResource($this->package)),
            'card' => $this->whenLoaded('card', fn() => new CardResource($this->card)),
        ];
    }
}
