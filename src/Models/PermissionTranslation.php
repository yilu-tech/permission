<?php

namespace YiluTech\Permission\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionTranslation extends Model
{
    protected $table = 'permission_translations';

    protected $fillable = ['permission', 'context', 'lang', 'description'];
}
