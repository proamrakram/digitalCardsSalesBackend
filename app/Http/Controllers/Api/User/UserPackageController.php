<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class UserPackageController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Package::class);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $filters = $request->only([
            'search',
            'category_id',
            'type',
            'status' => 'active'
        ]);

        $query = Package::query()
            ->filter($filters)
            ->orderByDesc('created_at');

        $query->with(['category', 'cards']);

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Packages list.',
            'data' => [
                'items' => PackageResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total_items' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Package $package)
    {
        $this->authorize('show', $package);

        // ✅ user لا يرى inactive packages
        if ($package->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Package not available.',
                'data' => null,
            ], 404);
        }
        $package->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Package details.',
            'data' => [
                'item' => new PackageResource($package),
            ],
        ]);
    }
}
