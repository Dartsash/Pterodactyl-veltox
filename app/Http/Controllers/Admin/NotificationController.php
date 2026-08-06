<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Pterodactyl\Models\Notification;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::with('admin')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $notifications,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $validated['admin_id'] = auth()->id();

        $notification = Notification::create($validated);

        return response()->json([
            'data' => $notification->load('admin'),
            'message' => 'Уведомление создано успешно.',
        ], 201);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'message' => 'sometimes|required|string',
            'is_active' => 'boolean',
        ]);

        $notification->update($validated);

        return response()->json([
            'data' => $notification->load('admin'),
            'message' => 'Уведомление обновлено успешно.',
        ]);
    }

    public function toggle(Notification $notification): JsonResponse
    {
        $notification->update([
            'is_active' => !$notification->is_active,
        ]);

        return response()->json([
            'data' => $notification,
            'message' => 'Статус уведомления изменен.',
        ]);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();

        return response()->json([
            'message' => 'Уведомление удалено успешно.',
        ]);
    }
}
