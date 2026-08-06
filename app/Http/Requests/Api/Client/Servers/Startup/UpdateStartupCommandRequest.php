<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Startup;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateStartupCommandRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_STARTUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            // "reset" restores the startup command defined by the server's egg.
            'mode' => 'required|in:auto,manual,reset',

            // Only used by the manual (administrator only) mode.
            'command' => 'nullable|string|max:2048',

            // Whitelisted switches used by the automatic mode.
            'options' => 'nullable|array',
            'options.memory' => 'nullable|integer|min:128|max:1048576',
            'options.aikar' => 'nullable|boolean',
            'options.ignore_java_version' => 'nullable|boolean',
            'options.utf8' => 'nullable|boolean',
            'options.console_compat' => 'nullable|boolean',
            'options.nogui' => 'nullable|boolean',
        ];
    }
}
