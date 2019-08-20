<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:45
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\Models\Permission;
use YiluTech\Permission\Models\PermissionTranslation;
use YiluTech\Permission\Models\Role;
use YiluTech\Permission\Util;

class PermissionController
{
    public function list()
    {
        if ($role_id = (int)\Request::input('role_id')) {
            $role = Role::findById($role_id);
            if (!$role) {
                throw new \Exception('role not found');
            }
            if (\Request::has('with_child')) {
                $permissions = $role->permissions();
            } else {
                $permissions = $role->includePermissions();
            }
        } else {
            $query = Permission::query();
            if (\Request::has('group')) {
                $query->where('group', \Request::input('group'));
            }
            $permissions = $query->get();
        }

        $translations = PermissionTranslation::query()->where('lang', app()->getLocale())->get();
        return $permissions->each(function ($item) use ($translations) {
            foreach ($translations as $translation) {
                if (Util::str_path_match($translation->name, $item->name)) {
                    $item->translation = $translation->content;
                    break;
                }
            }
        });
    }

    public function create()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/', 'unique:permissions,name'],
            'type' => 'required|string|max:16',
            'group' => 'nullable|string|max:16',
            'context' => 'nullable|array'
        ])->validate();
        return Permission::query()->create($attributes);
    }

    public function update()
    {
        $permission = Permission::findById(\Request::input('id', 0));
        if (!$permission) throw new \Exception('permission not exists');
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/', 'unique:permissions,name' . $attributes['id']],
            'type' => 'required|string|max:16',
            'group' => 'nullable|string|max:16',
            'context' => 'nullable|array'
        ])->validate();
        return $permission->save($attributes) ? 'success' : 'fail';
    }

    public function delete()
    {
        return \DB::translation(function () {
            Permission::query()->where('name', \Request::input('name'))->delete();
            PermissionTranslation::query()->where('name', \Request::input('name'))->delete();
            return 'success';
        });
    }

    public function translate()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^([a-zA-Z0-9]+|[*])(\.[a-zA-Z0-9]+)*$/'],
            'lang' => 'required|string|in:zh_CN,en',
            'content' => 'required|string|max:64',
            'description' => 'nullable|string|max:255'
        ])->validate();

        $translation = PermissionTranslation::query()
            ->where('name', $attributes['name'])
            ->where('lang', $attributes['lang'])
            ->first();
        if ($translation) {
            return $translation->update($attributes) ? 'success' : 'fail';
        }
        return PermissionTranslation::query()->create($attributes);
    }

    public function removeTranslation()
    {
        return PermissionTranslation::query()->where('name', \Request::input('name'))->delete();
    }
}
