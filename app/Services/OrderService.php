<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create a pending order AND reserve one available card atomically.
     * - Locks rows to prevent double-selling.
     */
    public function createPendingOrder(
        array $data,
        object $user
    ): Order {

        $userId = $user->id;
        $packageId = $data['package_id'];
        $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
        $paymentProofUrl = $data['payment_proof_url'] ?? null;
        $notes = $data['notes'] ?? null;

        return DB::transaction(function () use ($userId, $packageId, $paymentMethod, $paymentProofUrl, $notes) {
            /** @var Package|null $package */
            $package = Package::query()
                ->where('id', $packageId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$package) {
                throw ValidationException::withMessages([
                    'package_id' => ['Invalid or inactive package.'],
                ]);
            }

            // Pick one available card for this package with row lock.
            /** @var Card|null $card */
            $card = Card::query()
                ->where('package_id', $packageId)
                ->where('status', 'available')
                ->orderBy('created_at') // FIFO
                ->lockForUpdate()
                ->first();

            if (!$card) {
                throw ValidationException::withMessages([
                    'package_id' => ['No available cards for this package.'],
                ]);
            }

            // Reserve card
            $card->update([
                'status' => 'reserved',
                'reserved_by' => $userId,
                'reserved_at' => now(),
            ]);

            // Create order
            $order = Order::create([
                'user_id' => $userId,
                'card_id' => $card->id,
                'package_id' => $package->id,
                'status' => 'pending',
                'payment_method' => $paymentMethod ?: 'bank_transfer',
                'payment_proof_url' => $paymentProofUrl,
                'total_price' => $package->price,
                'notes' => $notes,
                'confirmed_at' => null,
            ]);

            return $order;
        }, 3);
    }

    /**
     * Confirm a pending order (Admin action).
     * - Locks order + card
     * - Marks card sold
     * - Confirms order
     */
    public function confirmOrder(string $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            /** @var Order|null $order */
            $order = Order::query()
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw ValidationException::withMessages([
                    'order' => ['Order not found.'],
                ]);
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Only pending orders can be confirmed.'],
                ]);
            }

            if (!$order->card_id) {
                throw ValidationException::withMessages([
                    'card_id' => ['Order has no card assigned.'],
                ]);
            }

            /** @var Card|null $card */
            $card = Card::query()
                ->where('id', $order->card_id)
                ->lockForUpdate()
                ->first();

            if (!$card) {
                throw ValidationException::withMessages([
                    'card' => ['Card not found.'],
                ]);
            }

            // Ensure card is reserved for the same user (or at least reserved).
            if ($card->status !== 'reserved') {
                throw ValidationException::withMessages([
                    'card_status' => ['Card is not reserved.'],
                ]);
            }

            // Confirm + sell
            $now = now();

            $card->update([
                'status' => 'sold',
                'sold_at' => $now,
            ]);

            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => $now,
            ]);

            return $order->fresh()->load(['package', 'card', 'user']);
        }, 3);
    }

    /**
     * Cancel order.
     * - Admin can cancel any
     * - User can cancel own pending (Policy already handles)
     * - If order is pending and card reserved by user => release card back to available.
     */
    public function cancelOrder(string $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            /** @var Order|null $order */
            $order = Order::query()
                ->where('id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw ValidationException::withMessages([
                    'order' => ['Order not found.'],
                ]);
            }

            if ($order->status === 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => ['Confirmed orders cannot be cancelled.'],
                ]);
            }

            if ($order->status === 'cancelled') {
                return $order->load(['package', 'card', 'user']);
            }

            // Release reserved card if exists and still reserved
            if ($order->card_id) {
                /** @var Card|null $card */
                $card = Card::query()
                    ->where('id', $order->card_id)
                    ->lockForUpdate()
                    ->first();

                if ($card && $card->status === 'reserved') {
                    $card->update([
                        'status' => 'available',
                        'reserved_by' => null,
                        'reserved_at' => null,
                    ]);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'confirmed_at' => null,
            ]);

            return $order->fresh()->load(['package', 'card', 'user']);
        }, 3);
    }
}
