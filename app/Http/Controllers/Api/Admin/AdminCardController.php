<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkImportCardsRequest;
use App\Http\Requests\StoreCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Services\CardService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminCardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CardService $cardService) {}

    /**
     * GET /api/cards (Admin)
     * Filters: package_id, status
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Card::class);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $filters = $request->only([
            'search', // search by username or uuid
            'package_id',
            'user_id',
            'status', // available, reserved, sold
            'reserved_at',
            'sold_at',
        ]);

        $query = Card::query()
            ->filter($filters)
            ->orderByDesc('created_at');

        $query->with('package');

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Cards list.',
            'data' => [
                'items' => CardResource::collection($paginator->items()),
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
     * POST /api/cards (Admin)
     */
    public function store(StoreCardRequest $request)
    {
        $this->authorize('create', Card::class);

        $card = $this->cardService->createCard($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Card created.',
            'data' => [
                'card' => new CardResource($card),
            ],
        ], 201);
    }

    /**
     * POST /api/cards/import (Admin)
     * Bulk import: { package_id, items: [{username,password}, ...] }
     */
    public function import(BulkImportCardsRequest $request)
    {
        $this->authorize('create', Card::class);

        $result = $this->cardService->bulkImport(
            packageId: $request->input('package_id'),
            items: $request->input('items')
        );

        return response()->json([
            'success' => true,
            'message' => $result['skipped'] > 0
                ? 'Imported with warnings (some rows skipped).'
                : 'Cards imported.',
            'data' => $result,
        ]);
    }

    /**
     * GET /api/cards/{card}/credentials (Admin OR the buyer of a confirmed order)
     * Uses CardPolicy@viewCredentials
     */
    public function credentials(Request $request, Card $card)
    {
        $this->authorize('viewCredentials', $card);

        // هنا فقط نُظهر بيانات الدخول
        return response()->json([
            'success' => true,
            'message' => 'Card credentials.',
            'data' => [
                'id' => (string) $card->id,
                'package_id' => (string) $card->package_id,
                'username' => $card->username,
                'password' => $card->password, // decrypted via accessor
            ],
        ]);
    }
}
