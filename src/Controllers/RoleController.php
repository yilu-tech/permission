<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:34
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\CacheManager;
use YiluTech\Permission\Helper\RoleGroup;
use YiluTech\Permission\Models\Role;

class RoleController
{
    public function list()
    {
        return Role::status(RS_READ, RoleGroup::getFromQuery())
            ->leftJoin('role_has_roles', 'role_has_roles.role_id', 'roles.id')
            ->select('roles.*', \DB::raw('group_concat(child_id separator ",") as child_keys'))
            ->groupBy('id')->get()->each(function ($item) {
                $item->child_keys = $item->child_keys ? explode(',', $item->child_keys) : [];
            });
    }

    public function create()
    {
        $group = RoleGroup::getFromQuery();
        $nameUniqueRule = 'unique:roles,name';
        if ($group !== false) {
            $nameUniqueRule .= ',NULL,id,group,' . ($group ?: 'NULL');
        }
        \Request::validate([
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}_-]{2,16}$/u', $nameUniqueRule],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'group' => 'nullable|string',
            'roles' => 'array',
            'permissions' => 'array'
        ]);
        $data = \Request::only(['name', 'description', 'config', 'roles', 'permissions']);
        if ($group) {
            $data['group'] = $group;
        }
        return \DB::transaction(function () use ($data) {
            $data['status'] = RS_EXTEND | RS_READ | RS_WRITE;
            if (!empty($data['roles'])) {
                $data['status'] = $data['status'] | RS_EXTENDED;
            }
            $role = Role::create($data);
            if ($data['status'] & RS_EXTENDED) {
                $role->giveChildRoleTo($data['roles']);
            }
            if (!empty($data['permissions'])) {
                $role->givePermissionTo($data['permissions']);
            }
            return $role;
        });
    }

    public function update()
    {
        $group = RoleGroup::getFromQuery();
        $nameUniqueRule = 'unique:roles,name,' . \Request::input('role_id') . ',id';
        if ($group !== false) {
            $nameUniqueRule .= ',group,' . ($group ?: 'NULL');
        }
        \Request::validate([
            'role_id' => 'required|integer',
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}_-]{2,16}$/u', $nameUniqueRule],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'roles' => 'array',
            'permissions' => 'array'
        ]);

        $role = Role::findById(\Request::input('role_id'), $group);
        if (!$role) throw new \Exception('Role not found.');

        if (!($role->status & RS_WRITE)) {
            throw new \Exception('Role not allow edit.');
        }

        $data = \Request::only(['name', 'description', 'config', 'roles', 'permissions']);
        $data['status'] = $role->status & ~RS_EXTENDED;

        if (!empty($data['roles'])) {
            if ($data['status'] & RS_SYS) {
                throw new \Exception('system role can not extend other role.');
            }
            $data['status'] = $data['status'] | RS_EXTENDED;
        }

        return \DB::transaction(function () use ($role, $data) {
            $role->update($data);
            if ($data['status'] & RS_EXTENDED) {
                $role->syncChildRoles($data['roles']);
            }
            $role->syncPermissions($data['permissions'] ?? []);

            resolve(CacheManager::class)->empty($role);

            return $role;
        });
    }

    public function delete()
    {
        \Request::validate(['role_id' => 'required|integer']);

        $role = Role::findById(\Request::input('role_id'), RoleGroup::getFromQuery());
        if (!$role) throw new \Exception('role not found');

        if ($role->status & RS_SYS) {
            throw new \Exception('can not remove');
        }

        return \DB::transaction(function () use ($role) {
            \DB::table('role_has_roles')->where('role_id', $role->id)->orWhere('child_id', $role->id)->delete();
            \DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            \DB::table('user_has_roles')->where('role_id', $role->id)->delete();
            $role->delete();
            return ['data' => 'success'];
        });
    }
}
