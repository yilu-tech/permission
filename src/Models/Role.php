<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use YiluTech\Permission\IdentityUtil;
use YiluTech\Permission\Traits\HasChildRoles;
use YiluTech\Permission\Traits\HasPermissions;

class Role extends Model
{
    use HasPermissions, HasChildRoles;

    protected $table = 'roles';

    protected $fillable = ['id', 'name', 'config', 'description', 'child_length'];

    protected $casts = [
        'config' => 'json',
    ];

    public function __construct(array $attributes = [])
    {
        $this->fillable = array_merge($this->fillable, IdentityUtil::getScopeKeys());

        parent::__construct($attributes);
    }

    /**
     * @param $attributes
     * @param null $identifier
     * @return $this
     */
    public static function create($attributes, $identifier = null)
    {
        if (config('permission.identity.names')) {
            $identifier = IdentityUtil::formatIdentity($identifier ?? []);
            $attributes = array_merge($attributes, array_combine(IdentityUtil::getScopeKeys(), $identifier));
        }
        return static::query()->create($attributes);
    }

    public static function findById(int $id)
    {
        return static::query()->where('id', $id)->first();
    }

    public static function findByName(string $name)
    {
        return static::query()->where('name', $name)->first();
    }

    public function users()
    {
        return $this->belongsToMany(config('permission.user.model'), 'user_has_roles', 'role_id', 'user_id');
    }
}
