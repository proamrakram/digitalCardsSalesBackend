<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('phone', $request->phone)->first();
        // $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        // ✅ Sync (safety): ensure Spatie role matches users.role
        $this->syncSpatieRoleWithColumn($user);

        // Load roles/permissions for response
        $user->load('roles', 'permissions');

        // Optional: Sanctum abilities based on role column
        $abilities = $user->role === 'admin'
            ? ['admin:*']
            : ['user:*'];

        $tokenName = $request->device_name
            ?? ($request->header('User-Agent') ?? 'api-client');

        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => new AuthResource($user, $token),
        ]);
    }

    public function register(RegisterRequest $request)
    {
        // ✅ default role in column
        $role = 'user';

        $user = User::create([
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            // 'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        // ✅ Sync to spatie roles
        $this->syncSpatieRoleWithColumn($user);

        $user->load('roles', 'permissions');

        $tokenName = $request->header('User-Agent') ?? 'api-client';
        $token = $user->createToken($tokenName, ['user:*'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registered successfully.',
            'data' => new AuthResource($user, $token),
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        // ✅ Ensure sync always (optional but safe)
        $this->syncSpatieRoleWithColumn($user);

        $user->load('roles.permissions');

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data' => new AuthResource($user),
        ]);
    }

    /**
     * Keep Spatie role in-sync with users.role column.
     * - If users.role = admin => assignRole('admin') and remove 'user'
     * - If users.role = user  => assignRole('user') and remove 'admin'
     */
    private function syncSpatieRoleWithColumn(User $user): void
    {
        $role = $user->role;

        if (! in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
        }

        $guard = config('auth.defaults.guard', 'sanctum');

        Role::firstOrCreate([
            'name' => $role,
            'guard_name' => $guard,
        ]);

        // ✅ Avoid unnecessary writes
        if (! $user->hasRole($role)) {
            $user->syncRoles([$role]);
        }
    }
}
