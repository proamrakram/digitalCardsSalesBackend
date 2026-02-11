<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Card;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable'],
            'payment_method' => ['nullable'],
            'user_id' => ['nullable', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Order::query()
            ->with([
                'user:id,uuid,full_name,phone,email,username,role',
                'package:id,uuid,name,name_ar,duration,price,status,type,category_id',
                'package.category:id,uuid,name,name_ar,type',
            ])
            ->latest('id');

        $query->filters([
            'search' => $validated['search'] ?? null,
            'status' => array_values(array_filter((array) ($validated['status'] ?? []))),
            'payment_method' => array_values(array_filter((array) ($validated['payment_method'] ?? []))),
            'user_id' => $validated['user_id'] ?? null,
            'package_id' => $validated['package_id'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]);

        $paginated = $query->paginate($perPage);

        $packages = Package::query()
            ->select(['id', 'name_ar'])
            ->orderBy('name_ar')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Orders list.',
            'data' => [
                'items' => OrderResource::collection($paginated)->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total_items' => $paginated->total(),
                    'total_pages' => $paginated->lastPage(),
                ],
                'packages' => $packages,
            ],
        ]);
    }

    public function show(Order $order)
    {

        $order->load([
            'user:id,uuid,full_name,phone,email,username,role',
            'package:id,uuid,name,name_ar,duration,price,status,type,category_id',
            'package.category:id,uuid,name,name_ar,type',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order details.',
            'data' => [
                'order' => new OrderResource($order),
            ],
        ]);
    }


    public function confirm(Request $request, Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be confirmed.',
                'data' => null,
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($order) {
                $order->refresh();

                $cardsJson = is_array($order->cards) ? $order->cards : (json_decode($order->cards ?? '[]', true) ?: []);
                $cardIds = collect($cardsJson)->pluck('id')->filter()->map(fn($v) => (int) $v)->values()->all();

                if (empty($cardIds)) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => 'Order has no reserved cards.',
                            'data' => null,
                        ],
                    ];
                }

                // lock cards
                $cards = Card::query()
                    ->whereIn('id', $cardIds)
                    ->lockForUpdate()
                    ->get(['id', 'uuid', 'status', 'reserved_at', 'sold_at', 'username', 'password']);

                // ensure still reserved
                $notReserved = $cards->first(fn($c) => $c->status !== 'reserved');
                if ($notReserved) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => 'Some cards are not reserved anymore.',
                            'data' => null,
                        ],
                    ];
                }

                $now = now();

                Card::query()
                    ->whereIn('id', $cardIds)
                    ->update([
                        'status' => 'sold',
                        'sold_at' => $now,
                    ]);

                $order->update([
                    'status' => 'confirmed',
                    'confirmed_at' => $now,
                    'cancelled_at' => null,
                ]);

                // update stored json snapshot (optional)
                $newCardsJson = $cards->map(function ($c) use ($now) {
                    return [
                        'id' => (string) $c->id,
                        'uuid' => (string) $c->uuid,
                        'status' => 'sold',
                        'reserved_at' => $c->reserved_at ? $c->reserved_at->format('Y-m-d H:i') : null,
                        'sold_at' => $now->format('Y-m-d H:i'),
                        'username' => $c->username,
                        'password' => $c->password,
                    ];
                })->values()->all();

                $order->update(['cards' => $newCardsJson]);

                $order->load([
                    'user:id,full_name,phone',
                    'package:id,name,name_ar,duration,price,status,type,category_id',
                    'package.category:id,name,name_ar,type',
                ]);

                return [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        'success' => true,
                        'message' => 'Order confirmed successfully.',
                        'data' => [
                            'order' => new OrderResource($order),
                        ],
                    ],
                ];
            }, 3);

            return response()->json($result['data'], $result['status']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error while confirming order.',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    public function cancel(Request $request, Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be cancelled.',
                'data' => null,
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($order) {
                $order->refresh();

                $cardsJson = is_array($order->cards) ? $order->cards : (json_decode($order->cards ?? '[]', true) ?: []);
                $cardIds = collect($cardsJson)->pluck('id')->filter()->map(fn($v) => (int) $v)->values()->all();

                $now = now();

                if (!empty($cardIds)) {
                    // lock + release
                    Card::query()
                        ->whereIn('id', $cardIds)
                        ->lockForUpdate()
                        ->get();

                    Card::query()
                        ->whereIn('id', $cardIds)
                        ->update([
                            'status' => 'available',
                            'reserved_at' => null,
                            'user_id' => null,
                        ]);
                }

                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => $now,
                ]);

                // keep json snapshot but mark cancelled
                $newCardsJson = collect($cardsJson)->map(function ($c) {
                    $c['status'] = 'available';
                    return $c;
                })->values()->all();

                $order->update(['cards' => $newCardsJson]);

                $order->load([
                    'user:id,full_name,phone',
                    'package:id,name,name_ar,duration,price,status,type,category_id',
                    'package.category:id,name,name_ar,type',
                ]);

                return [
                    'ok' => true,
                    'status' => 200,
                    'data' => [
                        'success' => true,
                        'message' => 'Order cancelled successfully.',
                        'data' => [
                            'order' => new OrderResource($order),
                        ],
                    ],
                ];
            }, 3);

            return response()->json($result['data'], $result['status']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error while cancelling order.',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }
}
