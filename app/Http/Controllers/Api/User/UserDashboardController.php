<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserDashboardOverviewResource;
use App\Models\Card;
use App\Models\Order;
use App\Models\Package;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{

    public function overview(Request $request)
    {
        $user = $request->user();

        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $search = $request->input('search');
        $status = (array) $request->input('status', []);
        $paymentMethod = (array) $request->input('payment_method', []);
        $from = $request->input('from'); // YYYY-MM-DD
        $to = $request->input('to');     // YYYY-MM-DD

        $base = Order::query()
            ->where('user_id', $user->id)
            ->with(['package:id,name_ar,duration,price,type']);

        // filters
        if ($search) {
            $base->where(function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where('uuid', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        if (!empty($status)) {
            $base->whereIn('status', $status);
        }

        if (!empty($paymentMethod)) {
            $base->whereIn('payment_method', $paymentMethod);
        }

        if ($from) {
            $base->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $base->whereDate('created_at', '<=', $to);
        }

        // stats (unfiltered by date/search/payment unless you want same filters: clone base if needed)
        $statsQuery = Order::query()->where('user_id', $user->id);

        $stats = [
            'total' => (int) $statsQuery->count(),
            'pending' => (int) (clone $statsQuery)->where('status', 'pending')->count(),
            'confirmed' => (int) (clone $statsQuery)->where('status', 'confirmed')->count(),
            'cancelled' => (int) (clone $statsQuery)->where('status', 'cancelled')->count(),
        ];

        $orders = (clone $base)
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $canViewCreds = $user->can('view card credentials');

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview.',
            'data' => new UserDashboardOverviewResource([
                'user' => $user,
                'stats' => $stats,
                'orders' => $orders,
                'can_view_card_credentials' => $canViewCreds,
            ]),
        ]);
    }
}
