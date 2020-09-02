<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionLog extends Model
{
    protected $table = 'permission_logs';

    protected $fillable = ['name', 'action', 'content', 'created_at'];

    public $timestamps = false;

    protected $casts = [
        'content' => 'json',
    ];

    public static function insert($name, $action, $content = null)
    {
        $data = compact('name', 'action', 'content');
        $data['created_at'] = date('Y-m-d H:i:s');
        return static::create($data);
    }
}
