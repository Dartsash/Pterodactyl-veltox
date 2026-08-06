<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerAddon extends Model
{
    public const RESOURCE_NAME = 'server_addon';
    protected $table = 'server_addons';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['server_id' => 'integer', 'enabled' => 'boolean', 'installed_at' => 'datetime'];
    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'addon_id' => 'required|string',
        'version' => 'required|string',
        'enabled' => 'sometimes|boolean',
    ];
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
