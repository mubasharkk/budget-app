<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use CrudTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}