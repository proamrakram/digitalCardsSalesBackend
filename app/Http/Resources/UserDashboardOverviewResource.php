<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDashboardOverviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $stats = $this->resource['stats'];
        $orders = $this->resource['orders'];
        $can = (bool) ($this->resource['can_view_card_credentials'] ?? false);

        return [
            'user' => [
                'id' => (string) $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
            ],
            'stats' => $stats,

            'orders' => [
                'items' => $orders->getCollection()->map(function ($o) use ($can) {
                    $cards = is_array($o->cards) ? $o->cards : (json_decode($o->cards ?? '[]', true) ?: []);

                    // إخفاء بيانات الدخول إلا إذا: confirmed + permission
                    $cardsSafe = array_map(function ($c) use ($o, $can) {
                        $isConfirmed = $o->status === 'confirmed';

                        return [
                            'id' => isset($c['id']) ? (string) $c['id'] : null,
                            'uuid' => $c['uuid'] ?? null,
                            'status' => $c['status'] ?? null,
                            'reserved_at' => $c['reserved_at'] ?? null,

                            'username' => ($isConfirmed && $can) ? ($c['username'] ?? null) : null,
                            'password' => ($isConfirmed && $can) ? ($c['password'] ?? null) : null,
                        ];
                    }, $cards);

                    return [
                        'id' => (string) $o->id,
                        'uuid' => $o->uuid,
                        'status' => $o->status,
                        'payment_method' => $o->payment_method,

                        'quantity' => (int) $o->quantity,
                        'amount' => (string) $o->amount,
                        'price' => (string) $o->price,
                        'total_price' => (string) $o->total_price,

                        'notes' => $o->notes,
                        'created_at' => optional($o->created_at)->format('Y-m-d H:i'),

                        'package' => $o->package ? [
                            'id' => (string) $o->package->id,
                            'name_ar' => $o->package->name_ar,
                            'duration' => $o->package->duration,
                            'price' => (string) $o->package->price,
                            'type' => $o->package->type,
                        ] : null,

                        'cards' => $cardsSafe,
                    ];
                })->values(),

                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total_items' => $orders->total(),
                    'total_pages' => $orders->lastPage(),
                ],
            ],
        ];
    }
}
