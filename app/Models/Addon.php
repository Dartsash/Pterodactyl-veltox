<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $table = 'addons';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'rating' => 'float',
    ];
}
