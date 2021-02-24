<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = ['name', 'type', 'scopes', 'content', 'translations', 'version', 'created_at', 'updated_at'];

    public $timestamps = false;

    protected $casts = [
        'config' => 'json',
        'content' => 'json',
        'scopes' => 'json',
        'translations' => 'json'
    ];

    protected $hidden = [
        'version',
        'created_at',
        'updated_at'
    ];

    public static function query($scope = false, $lang = null, $query = null)
    {
        if (!$query) {
            $query = parent::query();
        }
        $query->select('permissions.*');
        if ($scope) {
            $query->whereRaw("JSON_SEARCH(`scopes`, 'one', '$scope') IS NOT NULL");
        }
        if ($lang === null) {
            $lang = app()->getLocale();
        }
        if ($lang) {
            $query->addSelect(\DB::raw(sprintf('JSON_EXTRACT(`translations`, \'$."%s"\') as translations', $lang)));
        }
        return $query;
    }

    public static function findById($id, $scope = false)
    {
        if (is_array($id) || $id instanceof Collection) {
            return static::query($scope)->findMany($id);
        }
        return static::query($scope)->find($id);
    }

    public static function findByName(string $name, $scope = false)
    {
        return static::query($scope)->where('name', $name)->first();
    }
}
