<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Players;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdatePlayersRequest extends ClientApiRequest
{
    /**
     * Changing a list rewrites a file in the server root, so the file update
     * permission is required.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_UPDATE;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target' => is_null($this->input('target')) ? null : trim((string) $this->input('target')),
            'reason' => is_null($this->input('reason')) ? null : (string) $this->input('reason'),
        ]);
    }

    public function rules(): array
    {
        return [
            'target' => 'required|string|max:64',
            'reason' => 'nullable|string|max:200',
            'level' => 'nullable|integer|min:1|max:4',
            'bypasses_player_limit' => 'sometimes|boolean',
        ];
    }
}
