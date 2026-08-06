<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Addons\AnnouncementService;

/**
 * Exposes the panel-wide announcement to the React frontend.
 *
 * Endpoint: GET /api/client/announcement
 */
class AnnouncementController extends Controller
{
    public function __construct(private AnnouncementService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $isAdmin = (bool) optional($request->user())->root_admin;

        return new JsonResponse([
            'object' => 'announcement',
            'attributes' => $this->service->toArray($isAdmin),
        ]);
    }
}
