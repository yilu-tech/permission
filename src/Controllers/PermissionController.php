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
            return $role->permissions();
        }
        return Permission::query(\Request::input('scope', false))->get();
    }

    public function create()
    {
        \Request::validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/', 'unique:permissions,name'],
            'type' => 'required|string|max:16',
            'scopes' => 'array',
            'content' => 'nullable|array'
        ]);
        $data = \Request::input();
        if (empty($data['scopes'])) {
            $data['scopes'] = [];
        }
        return Permission::query()->create($data);
    }

    public function update()
    {
        $permission = Permission::findById(\Request::input('permission_id'));
        if (!$permission) throw new \Exception('permission not exists');
        $data = \Request::input();
        \Request::validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)*$/', 'unique:permissions,name,' . $data['permission_id']],
            'type' => 'string|max:16',
            'scopes' => 'array',
            'content' => 'nullable|array'
        ]);
        $permission->update($data);
        return $permission;
    }

    public function delete()
    {
        if (\Request::has('permission_id')) {
            Permission::query()->where('id', \Request::input('permission_id'))->delete();
        } else {
            Permission::query()->where('name', \Request::input('name'))->delete();
        }
        return ['data' => 'success'];
    }

    public function translate()
    {
        \Request::validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^([a-zA-Z0-9]+|[*])(\.[a-zA-Z0-9]+)*$/'],
            'lang' => 'required|string|in:zh_CN,en',
            'content' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:255'
        ]);

        $permission = Permission::query(false, false)->where('name', \Request::input('name'))->first();
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
        return $permission;
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
                return $manager->localStore()->getLastUpdateTime();
            case 'update':
                $manager->localStore()->saveChanges(\Request::input('changes'));
                return 'SUCCESS';
            default:
                return 'FAIL';
        }
    }
}
