<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Mods;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class DeleteModRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_DELETE;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|string|max:180',
        ];
    }
}
