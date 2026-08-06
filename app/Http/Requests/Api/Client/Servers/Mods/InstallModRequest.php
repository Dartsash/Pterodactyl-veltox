<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Mods;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class InstallModRequest extends ClientApiRequest
{
    /**
     * Installing pulls a new jar into /mods, which is a file creation.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_CREATE;
    }

    public function rules(): array
    {
        return [
            'version' => 'nullable|string|max:64',
            'loader' => 'nullable|string|max:32',
            'game_version' => 'nullable|string|max:32',
            'dependencies' => 'nullable|boolean',
        ];
    }
}
