<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Players;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetPlayersRequest extends ClientApiRequest
{
    /**
     * The lists live in files in the server root, so reading them needs the
     * same permission as reading a file.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_READ_CONTENT;
    }
}
