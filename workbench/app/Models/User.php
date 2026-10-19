<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 */
class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'is_admin'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['is_admin' => 'boolean'];

    /** The method lu::models.super_admin_method names: an admin bypasses the policies. */
    public function is_super_admin(): bool
    {
        return $this->is_admin;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
