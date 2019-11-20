<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:45
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\Models\Permission;
use YiluTech\Permission\Models\Role;
use YiluTech\Permission\PermissionManager;

class PermissionController
{
    public function list()
    {
        if ($role_id = (int)\Request::input('role_id')) {
            $role = Role::findById($role_id);
            if (!$role) {
                throw new \Exception('Role not found');
            }
            if (\Request::has('with_child')) {
                return $role->permissions();
            }
            return $role->includePermissions();
        }
        return Permission::query(\Request::input('scope', false))->get();
    }

    public function create()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/', 'unique:permissions,name'],
            'type' => 'required|string|max:16',
            'scopes' => 'array',
            'content' => 'nullable|array'
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
            'scopes' => 'array',
            'content' => 'nullable|array'
        ])->validate();
        return $permission->save($attributes) ? 'success' : 'fail';
    }

    public function delete()
    {
        return \DB::translation(function () {
            Permission::query()->where('name', \Request::input('name'))->delete();
            return 'success';
        });
    }

    public function translate()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^([a-zA-Z0-9]+|[*])(\.[a-zA-Z0-9]+)*$/'],
            'lang' => 'required|string|in:zh_CN,en',
            'content' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255'
        ])->validate();
        $permission = Permission::findByName(\Request::input('name'));
        if (!$permission) {
            throw new \Exception('Permission not found');
        }
        $lang = \Request::input('lang');
        $data = [
            'name' => \Request::input('content'),
            'desc' => \Request::input('description')
        ];

        $translations = $permission->translations;

        if ($data['name']) {
            if ($translations) {
                $translations[$lang] = $data;
            } else {
                $translations = [$lang => $data];
            }
        } else {
            unset($translations[$lang]);
        }
        $permission->update(compact('translations'));
        return 'success';
    }

    public function sync()
    {
        \Request::validate([
            'action' => 'required|in:update,getLastUpdateTime',
            'changes' => 'required_if:action,update|array|min:1'
        ]);
        $manager = new PermissionManager(\Request::input('server'));
        switch (\Request::input('action')) {
            case 'getLastUpdateTime':
                return $manager->getLastUpdateTime();
            case 'update':
                $manager->writeDB(\Request::input('changes'));
                return 'SUCCESS';
            default:
                return 'FAIL';
        }
    }
}
