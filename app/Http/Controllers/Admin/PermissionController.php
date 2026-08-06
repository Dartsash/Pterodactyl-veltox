<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Pterodactyl\Models\AdminPermission;
use Pterodactyl\Models\User;
use Pterodactyl\Models\UserPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $per_page = $request->query('per_page', 15);

        $query = AdminPermission::query();

        if ($search) {
            $query->search($search);
        }

        $permissions = $query->paginate($per_page);

        return response()->json([
            'success' => true,
            'data' => $permissions->items(),
            'pagination' => [
                'current_page' => $permissions->currentPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
                'last_page' => $permissions->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:admin_permissions,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        try {
            $permission = AdminPermission::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Разрешение создано успешно',
                'data' => $permission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании разрешения',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, AdminPermission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:admin_permissions,name,' . $permission->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        try {
            $permission->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Разрешение обновлено успешно',
                'data' => $permission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении разрешения',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(AdminPermission $permission): JsonResponse
    {
        try {
            UserPermission::where('permission_id', $permission->id)->delete();
            $permission->delete();

            return response()->json([
                'success' => true,
                'message' => 'Разрешение удалено успешно',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении разрешения',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignPermission(Request $request, User $user, AdminPermission $permission): JsonResponse
    {
        try {
            $existing = UserPermission::where('user_id', $user->id)
                ->where('permission_id', $permission->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'У пользователя уже есть это разрешение',
                ], 409);
            }

            $userPermission = UserPermission::create([
                'user_id' => $user->id,
                'permission_id' => $permission->id,
                'granted_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Разрешение назначено успешно',
                'data' => $userPermission->load('permission', 'grantedBy'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при назначении разрешения',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function revokePermission(User $user, AdminPermission $permission): JsonResponse
    {
        try {
            $deleted = UserPermission::where('user_id', $user->id)
                ->where('permission_id', $permission->id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Разрешение пользователя не найдено',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Разрешение отозвано успешно',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при отзыве разрешения',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getUserPermissions(User $user): JsonResponse
    {
        try {
            $permissions = $user->adminPermissions()
                ->where('is_active', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении разрешений',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
