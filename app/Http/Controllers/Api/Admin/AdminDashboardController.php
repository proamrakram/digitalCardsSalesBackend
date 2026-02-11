<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/overview
     * KPIs: orders, cards, revenue, users
     */
    public function overview(Request $request)
    {
        $now = now();

        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $confirmedOrders = Order::where('status', 'confirmed')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $totalCards = Card::count();
        $availableCards = Card::where('status', 'available')->count();
        $reservedCards = Card::where('status', 'reserved')->count();
        $soldCards = Card::where('status', 'sold')->count();

        $totalRevenue = Order::where('status', 'confirmed')->sum('total_price');

        $totalUsers = User::count();
        $newUsersLast7Days = User::where('created_at', '>=', $now->copy()->subDays(7))->count();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview.',
            'data' => [
                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'confirmed' => $confirmedOrders,
                    'cancelled' => $cancelledOrders,
                ],
                'cards' => [
                    'total' => $totalCards,
                    'available' => $availableCards,
                    'reserved' => $reservedCards,
                    'sold' => $soldCards,
                ],
                'revenue' => [
                    'total_confirmed' => (string) $totalRevenue,
                ],
                'users' => [
                    'total' => $totalUsers,
                    'new_last_7_days' => $newUsersLast7Days,
                ],
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/cards-status
     * For pie/donut chart
     */
    public function cardsStatus(Request $request)
    {
        $counts = Card::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'message' => 'Cards status summary.',
            'data' => [
                'available' => (int) ($counts['available'] ?? 0),
                'reserved' => (int) ($counts['reserved'] ?? 0),
                'sold' => (int) ($counts['sold'] ?? 0),
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/orders-timeseries?days=30
     * Returns daily orders count + revenue for charting.
     */
    public function ordersTimeseries(Request $request)
    {
        $days = (int) ($request->input('days', 30));
        $days = max(7, min($days, 365)); // sensible bounds for template

        $from = now()->startOfDay()->subDays($days - 1);
        $to = now()->endOfDay();

        // MySQL: DATE(created_at) grouping
        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN status = 'confirmed' THEN total_price ELSE 0 END) as revenue_confirmed")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        // Fill missing days with zeros (important for charts)
        $labels = [];
        $orders = [];
        $revenue = [];

        $cursor = $from->copy();
        while ($cursor <= $to) {
            $day = $cursor->toDateString();
            $labels[] = $day;

            $row = $rows->get($day);
            $orders[] = (int) ($row->orders_count ?? 0);
            $revenue[] = (string) ($row->revenue_confirmed ?? '0');

            $cursor->addDay();
        }

        return response()->json([
            'success' => true,
            'message' => 'Orders timeseries.',
            'data' => [
                'range' => [
                    'days' => $days,
                    'from' => $from->format("Y-m-d h:i"),
                    'to' => $to->format("Y-m-d h:i"),
                ],
                'labels' => $labels,
                'orders' => $orders,
                'revenue_confirmed' => $revenue,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/latest-orders?limit=10
     * Table widget in dashboard
     */
    public function latestOrders(Request $request)
    {
        $limit = (int) ($request->input('limit', 10));
        $limit = max(5, min($limit, 50));

        $orders = Order::query()
            ->with(['user:id,full_name,email', 'package:id,name,name_ar,duration,price', 'card:id,status'])
            ->latest()
            ->limit($limit)
            ->get();

        $items = $orders->map(function (Order $o) {
            return [
                'id' => (string) $o->id,
                'status' => $o->status,
                'total_price' => (string) $o->total_price,
                'payment_method' => $o->payment_method,
                'created_at' => $o->created_at?->format("Y-m-d H:i"),
                'confirmed_at' => $o->confirmed_at?->format("Y-m-d H:i"),

                'user' => $o->user ? [
                    'id' => (string) $o->user->id,
                    'full_name' => $o->user->full_name,
                    'email' => $o->user->email,
                ] : null,

                'package' => $o->package ? [
                    'id' => (string) $o->package->id,
                    'name' => $o->package->name,
                    'name_ar' => $o->package->name_ar,
                    'duration' => $o->package->duration,
                    'price' => (string) $o->package->price,
                ] : null,

                'card' => $o->card ? [
                    'id' => (string) $o->card->id,
                    'status' => $o->card->status,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Latest orders.',
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/packages-inventory
     * Shows inventory per package (available/reserved/sold)
     */
    public function packagesInventory(Request $request)
    {
        // Aggregate cards counts by package_id and status
        $rows = Card::query()
            ->select('package_id')
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved")
            ->selectRaw("SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold")
            ->groupBy('package_id')
            ->get()
            ->keyBy('package_id');

        $packages = Package::query()
            ->select('id', 'name', 'name_ar', 'duration', 'price', 'status')
            ->orderBy('created_at', 'desc')
            ->get();

        $items = $packages->map(function (Package $p) use ($rows) {
            $r = $rows->get($p->id);

            return [
                'package' => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'name_ar' => $p->name_ar,
                    'duration' => $p->duration,
                    'price' => (string) $p->price,
                    'status' => $p->status,
                ],
                'inventory' => [
                    'available' => (int) ($r->available ?? 0),
                    'reserved' => (int) ($r->reserved ?? 0),
                    'sold' => (int) ($r->sold ?? 0),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Packages inventory.',
            'data' => [
                'items' => $items,
            ],
        ]);
    }
}
