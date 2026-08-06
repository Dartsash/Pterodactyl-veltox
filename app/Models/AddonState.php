<?php

namespace Pterodactyl\Models;

class AddonState extends Model
{
    public const RESOURCE_NAME = 'addon_state';
    protected $table = 'addon_states';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['enabled' => 'boolean'];
    public static array $validationRules = [
        'addon_id' => 'required|string',
        'enabled' => 'required|boolean',
    ];
}
