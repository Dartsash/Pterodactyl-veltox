<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Mods;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ToggleModRequest extends ClientApiRequest
{
    /**
     * Enabling and disabling renames the file in place.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_UPDATE;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|string|max:180',
            'enabled' => 'required|boolean',
        ];
    }
}
