<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpKernel\HttpCache\Store;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/orders
     * admin: all orders
     * user: own orders only
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $filters = $request->only([
            'search', // search by uuid
            'status', // pending, confirmed, cancelled
            'payment_method', // BOP, cash, paypal
            'user_id',
            'package_id',
        ]);

        $query = Order::query()
            ->with(['user', 'package', 'card'])
            ->filter($filters)
            ->orderByDesc('created_at');

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Orders list.',
            'data' => [
                'items' => OrderResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total_items' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/orders/{order}
     */
    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['user', 'package', 'card']);

        return response()->json([
            'success' => true,
            'message' => 'Order details.',
            'data' => [
                'order' => new OrderResource($order),
            ],
        ]);
    }

    /**
     * POST /api/orders
     * Create pending order + reserve card
     */
    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $order = $this->orderService->createPendingOrder($request->validated(), $request->user());

        $order->load(['user', 'package', 'card']);

        return response()->json([
            'success' => true,
            'message' => 'Order created (pending).',
            'data' => [
                'order' => new OrderResource($order),
            ],
        ], 201);
    }

    /**
     * POST /api/orders/{order}/confirm
     * Admin only (Policy confirm)
     */
    public function confirm(Request $request, Order $order)
    {
        $this->authorize('confirm', $order);

        $confirmed = $this->orderService->confirmOrder((string) $order->id);

        return response()->json([
            'success' => true,
            'message' => 'Order confirmed.',
            'data' => [
                'order' => new OrderResource($confirmed),
            ],
        ]);
    }

    /**
     * POST /api/orders/{order}/cancel
     * Admin can cancel any; user can cancel own pending (Policy cancel)
     */
    public function cancel(Request $request, Order $order)
    {
        $this->authorize('cancel', $order);

        $cancelled = $this->orderService->cancelOrder((string) $order->id);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled.',
            'data' => [
                'order' => new OrderResource($cancelled),
            ],
        ]);
    }
}
