<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePackageRequest;
use App\Http\Requests\UpdatePackageRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\PackageResource;
use App\Models\Card;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminPackageController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/packages
     * Filters: category_id, type (via category), is_active, with_category=1
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Package::class);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $filters = $request->only([
            'search',
            'category_id',
            'status',
            'type',
        ]);

        $query = Package::query()
            ->filter($filters)
            ->orderByDesc('created_at');

        $query->with('category');
        $query->with('cards');

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

    /**
     * GET /api/packages/{package}
     */
    public function show(Request $request, Package $package)
    {
        $this->authorize('view', $package);

        if ($request->boolean('with_category')) {
            $package->load('category');
        }

        $package->load('cards');

        return response()->json([
            'success' => true,
            'message' => 'Package details.',
            'data' => [
                'item' => new PackageResource($package),
            ],
        ]);
    }

    /**
     * POST /api/packages (Admin)
     */
    public function store(StorePackageRequest $request)
    {
        $this->authorize('create', Package::class);

        $package = Package::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Package created.',
            'data' => [
                'package' => new PackageResource($package->load('category')),
            ],
        ], 201);
    }

    /**
     * PUT/PATCH /api/packages/{package} (Admin)
     */
    public function update(UpdatePackageRequest $request, Package $package)
    {
        $this->authorize('update', $package);

        $package->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Package updated.',
            'data' => [
                'package' => new PackageResource($package->fresh()->load('category')),
            ],
        ]);
    }

    /**
     * DELETE /api/packages/{package} (Admin)
     */
    public function destroy(Request $request, Package $package)
    {
        $this->authorize('delete', $package);

        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted.',
            'data' => null,
        ]);
    }

    /**
     * GET /api/admin/packages/{package}/cards (Admin)
     */

    public function cards(Request $request, Package $package)
    {
        // ?username=&status=&page=&per_page

        $this->authorize('view', $package);

        $perPage = (int) ($request->input('per_page', 20));
        $perPage = max(1, min($perPage, 200));

        $q = Card::query()
            ->where('package_id', $package->id)
            ->orderByDesc('created_at');

        $q->with('package');

        if ($request->filled('username')) {
            $q->where('username', 'like', '%' . $request->input('username') . '%');
        }

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }

        $items = $q->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Package cards.',
            'data' => [
                'items' => CardResource::collection($items->getCollection()),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total_items' => $items->total(),
                    'total_pages' => $items->lastPage(),
                ],
            ],
        ]);
    }
}
