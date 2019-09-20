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

    public static function queryWithLang(string $lang)
    {
        return parent::query()->leftJoin('permission_translations', function ($join) use ($lang) {
            $join->on('permission_translations.name', 'permissions.name')->on('lang', $lang);
        })->select('permissions.*', 'permission_translations.content as translate');
    }

    public static function findById(int $id, $group = false)
    {
        if ($group === false) {
            return static::query()->find($id);
        }
        return static::query()->where('group', $group)->find($id);
    }

    public static function findByName(string $name, $group = false)
    {
        $query = static::query()->where('name', $name);
        if ($group !== false) {
            $query->where('group', $group);
        }
        return $query->first();
    }
}
