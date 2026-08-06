<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Versions;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class InstallVersionRequest extends ClientApiRequest
{
    /**
     * Writing a new jar into the server is a file operation, so the same
     * permission as uploading a file is required.
     */
    public function permission(): string
    {
        return Permission::ACTION_FILE_CREATE;
    }

    /**
     * Build numbers arrive as integers from some upstream APIs, so normalise
     * everything to a string before the rules below run.
     */
    protected function prepareForValidation(): void
    {
        $build = $this->input('build');

        $this->merge([
            'build' => is_null($build) || $build === '' ? null : (string) $build,
            'version' => is_null($this->input('version')) ? null : (string) $this->input('version'),
            'wipe' => filter_var($this->input('wipe', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'core' => 'required|string|max:32',
            'version' => 'required|string|max:64',
            'build' => 'nullable|string|max:64',
            'wipe' => 'sometimes|boolean',
        ];
    }

    /**
     * Wiping the server root destroys data, so it additionally requires the
     * permission to delete files.
     */
    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        if (!$this->input('wipe')) {
            return true;
        }

        $server = $this->route()->parameter('server');

        if (!$server instanceof Server) {
            return false;
        }

        return $this->user()->can(Permission::ACTION_FILE_DELETE, $server);
    }
}
