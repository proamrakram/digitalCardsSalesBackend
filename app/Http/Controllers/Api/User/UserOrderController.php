<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserOrderRequest;
use App\Models\Card;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserOrderController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreUserOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $user = $request->user();

        $packageId = (int) $request->input('package_id');
        $quantity = (int) $request->input('quantity', 1);
        // $amount = (float) $request->input('amount');
        $paymentMethod = $request->input('payment_method', 'BOP');
        $notes = $request->input('notes');

        try {
            $result = DB::transaction(function () use ($user, $packageId, $quantity,  $paymentMethod, $notes) {

                // 1️⃣ Package active
                $package = Package::where('id', $packageId)
                    ->where('status', 'active')
                    ->first();

                if (! $package) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => 'Package not found or inactive.',
                            'data' => null,
                        ],
                    ];
                }

                // 2️⃣ Amount validation
                $unitPrice = (float) $package->price;
                $expectedTotal = round($unitPrice * $quantity, 2);

                // if ($amount <= 0 || abs($amount - $expectedTotal) > 0.00001) {
                //     return [
                //         'ok' => false,
                //         'status' => 422,
                //         'data' => [
                //             'success' => false,
                //             'message' => 'Invalid amount.',
                //             'data' => [
                //                 'unit_price' => (string) $package->price,
                //                 'quantity' => $quantity,
                //                 'expected_amount' => number_format($expectedTotal, 2, '.', ''),
                //                 'received_amount' => (string) $amount,
                //             ],
                //         ],
                //     ];
                // }

                // 3️⃣ Lock cards
                $cards = Card::where('package_id', $package->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($quantity)
                    ->get();

                if ($cards->count() < $quantity) {
                    return [
                        'ok' => false,
                        'status' => 422,
                        'data' => [
                            'success' => false,
                            'message' => 'Not enough available cards.',
                            'data' => [
                                'requested' => $quantity,
                                'available' => $cards->count(),
                            ],
                        ],
                    ];
                }

                // 4️⃣ Reserve cards
                $now = now();
                foreach ($cards as $card) {
                    $card->update([
                        'status' => 'reserved',
                        'reserved_at' => $now,
                        'user_id' => $user->id,
                    ]);
                }

                // 5️⃣ Prepare JSON cards payload
                $cardsJson = $cards->map(fn($card) => [
                    'id' => (string) $card->id,
                    'uuid' => $card->uuid,
                    'status' => $card->status,
                    'reserved_at' => $card->reserved_at?->format('Y-m-d H:i'),
                ])->values()->toArray();

                // 6️⃣ Create order
                $order = Order::create([
                    'payment_method' => $paymentMethod,
                    'payment_proof_url' => null,

                    'quantity' => $quantity,
                    'cards' => $cardsJson,           // ✅ JSON stored here
                    // 'amount' => $amount,
                    // 'price' => $package->price,
                    'total_price' => $expectedTotal,

                    'notes' => $notes,
                    'status' => 'pending',

                    'user_id' => $user->id,
                    'card_id' => $cards->first()->id, // legacy / quick access
                    'package_id' => $package->id,
                ]);

                return [
                    'ok' => true,
                    'status' => 201,
                    'data' => [
                        'success' => true,
                        'message' => 'Order created successfully.',
                        'data' => [
                            'order' => [
                                'id' => (string) $order->id,
                                'uuid' => $order->uuid,
                                'status' => $order->status,
                                'payment_method' => $order->payment_method,

                                'quantity' => $order->quantity,
                                'amount' => (string) $order->amount,
                                'price' => (string) $order->price,
                                'total_price' => (string) $order->total_price,

                                'cards' => $order->cards, // ✅ JSON
                                'created_at' => $order->created_at?->format('Y-m-d H:i'),
                            ],
                        ],
                    ],
                ];
            }, 3);

            return response()->json($result['data'], $result['status']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error while creating order.',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
