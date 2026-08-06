<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Требуется аутентификация',
            ], 401);
        }

        $user = auth()->user();

        // Проверяем, если пользователь root admin
        if (isset($user->is_root_admin) && $user->is_root_admin === 1) {
            return $next($request);
        }

        // Проверяем наличие любого из требуемых разрешений
        if (empty($permissions) || $user->hasAnyAdminPermission($permissions)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'У вас нет прав для выполнения этого действия',
        ], 403);
    }
}
