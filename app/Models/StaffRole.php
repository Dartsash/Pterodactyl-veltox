<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Backs the "Permission Management" addon.
 *
 * A staff role is a named bundle of admin sections a non root administrator is
 * allowed to open. Root administrators always bypass these checks.
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property array $permissions
 */
class StaffRole extends EloquentModel
{
    protected $table = 'staff_roles';

    protected $fillable = ['name', 'color', 'permissions'];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Whether this role may open the given admin section.
     */
    public function hasPermission(string $section): bool
    {
        return in_array($section, $this->permissions ?? [], true);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Pterodactyl\Models\User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'staff_role_id');
    }
}
