<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['id', 'name', 'type', 'scopes', 'content', 'translations'];

    protected $casts = [
        'config' => 'json',
        'content' => 'json',
        'scopes' => 'json',
        'translations' => 'json'
    ];

    public static function query($scope = false, $lang = null, $query = null)
    {
        if (!$query) {
            $query = parent::query();
        }
        if ($scope) {
            $query->whereRaw("JSON_SEARCH(`scopes`, 'one', '$scope') IS NOT NULL");
        }
        if (!$lang) {
            $lang = app()->getLocale();
        }
        return $query->select('permissions.*', \DB::raw("JSON_EXTRACT(`translations`, '$.$lang') as translations"));
    }

    public static function findById(int $id, $scope = false)
    {
        return static::query($scope)->find($id);
    }

    public static function findByName(string $name, $scope = false)
    {
        return static::query($scope)->where('name', $name)->first();
    }
}
