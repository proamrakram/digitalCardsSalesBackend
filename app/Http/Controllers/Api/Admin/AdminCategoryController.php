<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AdminCategoryController extends Controller
{
    use AuthorizesRequests;
    /**
     * GET /api/categories
     * Filters: type=hourly|monthly, with_packages=1
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Category::class);

        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Category::query()->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->boolean('with_packages')) {
            $query->with(['packages' => fn($q) => $q->orderBy('created_at', 'desc')]);
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Categories list.',
            'data' => [
                'items' => CategoryResource::collection($items),
                // (اختياري) meta إذا بدك pagination حقيقي بالفرونت
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total_items' => $items->total(),
                    'total_pages' => $items->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/categories/{category}
     */
    public function show(Request $request, Category $category)
    {
        $this->authorize('view', $category);

        if ($request->boolean('with_packages')) {
            $category->load(['packages' => fn($q) => $q->orderBy('created_at', 'desc')]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category details.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    /**
     * POST /api/categories (Admin)
     */
    public function store(StoreCategoryRequest $request)
    {
        $this->authorize('create', Category::class);

        $category = Category::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ], 201);
    }

    /**
     * PUT/PATCH /api/categories/{category} (Admin)
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    /**
     * DELETE /api/categories/{category} (Admin)
     */
    public function destroy(Request $request, Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted.',
            'data' => null,
        ]);
    }
}
