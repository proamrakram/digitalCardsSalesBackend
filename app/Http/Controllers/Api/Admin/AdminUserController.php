<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\Paginator;

class AdminUserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        // ✅ لأننا نستخدم POST بدل GET، لازم نضبط current page يدويًا
        Paginator::currentPageResolver(fn() => $page);

        $query = User::query()->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($x) use ($q) {
                $x->where('uuid', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($request->filled('role')) {
            // عمليًا نحن نسمح admin/user فقط
            $query->where('role', $request->input('role'));
        }

        // (اختياري) counts في القائمة
        if ($request->boolean('with_counts')) {
            $query->withCount(['orders', 'cards']);
        }

        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users list.',
            'data' => [
                'items' => UserResource::collection($items),
                'meta' => [
                    'current_page' => $items->currentPage(),
                    'per_page'     => $items->perPage(),
                    'total_items'  => $items->total(),
                    'total_pages'  => $items->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);

        // ✅ Counts for details page
        $user->loadCount(['orders', 'cards']);

        return response()->json([
            'success' => true,
            'message' => 'User details.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $user = User::create($request->validated());

        $this->syncSpatieRoleWithColumn($user);

        return response()->json([
            'success' => true,
            'message' => 'User created.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $this->ensureNotSuperAdmin($user);

        $user->update($request->validated());

        $this->syncSpatieRoleWithColumn($user);

        return response()->json([
            'success' => true,
            'message' => 'User updated.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        // لا تحذف نفسك
        if ($request->user()->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $this->ensureNotSuperAdmin($user);

        // لا تحذف آخر Admin
        if ($user->role === 'admin') {
            $adminsCount = User::query()->where('role', 'admin')->count();
            if ($adminsCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot delete the last admin.',
                ]);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted.',
            'data' => null,
        ]);
    }

    private function ensureNotSuperAdmin(User $user): void
    {
        $superAdminId = (int) config('app.super_admin_id', 1);

        if ((int) $user->id === $superAdminId) {
            throw ValidationException::withMessages([
                'user' => 'You cannot modify or delete the Super Admin account.',
            ]);
        }
    }

    private function syncSpatieRoleWithColumn(User $user): void
    {
        $role = $user->role;

        // ✅ عمليًا: admin/user فقط
        if (! in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
            $user->role = 'user';
            $user->save();
        }

        $guard = 'sanctum';

        \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => $role,
            'guard_name' => $guard,
        ]);

        $user->syncRoles([$role]);
    }
}
