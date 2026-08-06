<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Addons;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ToggleAddonStateRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    /**
     * Toggling an installed addon on or off is the same class of action as
     * installing or removing it, so it reuses that permission. It used to be
     * gated on `root_admin`, which made no sense on a per-server client route.
     */
    public function permission(): string
    {
        return Permission::ACTION_ADDON_INSTALL;
    }

    public function rules(): array
    {
        return ['enabled' => 'required|boolean'];
    }
}
