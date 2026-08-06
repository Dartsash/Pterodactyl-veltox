<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Properties;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateServerPropertiesRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_UPDATE;
    }

    /**
     * The per key rules are generated from the whitelist inside
     * ServerPropertiesService and applied by the controller.
     */
    public function rules(): array
    {
        return [
            'values' => 'required|array',
        ];
    }
}
