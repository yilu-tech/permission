<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['id', 'name', 'type', 'group', 'content'];

    protected $casts = [
        'config' => 'json',
        'content' => 'json'
    ];

    public static function findById(int $id)
    {
        return static::query()->where('id', $id)->first();
    }

    public static function findByName(string $name)
    {
        return static::query()->where('name', $name)->first();
    }
}
