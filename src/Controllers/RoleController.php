<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:34
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\Models\Role;

class RoleController
{
    protected function getRoleGroup()
    {
        if (!\Request::has('group')) {
            return null;
        }
        $group = config('permission.role.group_value.' . \Request::input('group'));
        if (!$group || !\Request::input($group)) {
            throw new \Exception('get role group error.');
        }
        return \Request::input('group') . ':' . \Request::input($group);
    }

    public function list()
    {
        return Role::query()->leftJoin('role_has_roles', 'role_has_roles.role_id', 'roles.id')
            ->select('roles.*', \DB::raw('group_concat(child_id separator ",") as child_keys'))
            ->where('group', $this->getRoleGroup())
            ->groupBy('id')->get()->each(function ($item) {
                $item->child_keys = $item->child_keys ? explode(',', $item->child_keys) : [];
            });
    }

    public function create()
    {
        \Request::validate([
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}_-]{2,16}$/u'],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'roles' => 'array',
            'permissions' => 'array'
        ]);
        $data = \Request::only(['name', 'description', 'config']);
        $data['group'] = $this->getRoleGroup();

        return \DB::transaction(function () use ($data) {
            $data['child_length'] = count($roles = \Request::input('roles', []));
            $role = Role::create($data);

            if ($data['child_length']) {
                $role->giveChildRoleTo($roles);
            }

            if (count($permissions = \Request::input('permissions', []))) {
                $role->givePermissionTo($permissions);
            }
            return $role;
        });
    }

    public function update()
    {
        \Request::validate([
            'role_id' => 'required|integer',
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}_-]{2,16}$/u'],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'roles' => 'array',
            'permissions' => 'array'
        ]);
        $role = Role::findById(\Request::input('role_id'));
        if (!$role) throw new \Exception('role not found');

        return \DB::transaction(function () use ($role) {
            $data = \Request::only(['name', 'description', 'config']);
            $data['child_length'] = count($roles = \Request::input('roles', []));
            $role->update($data);
            if ($data['child_length']) {
                $role->syncChildRoles($roles);
            }
            if (count($permissions = \Request::input('permissions', []))) {
                $role->syncPermissions($permissions);
            }
            return $role;
        });
    }

    public function delete()
    {
        \Request::validate(['role_id' => 'required|integer']);
        return \DB::transaction(function () {
            $role_id = \Request::input('role_id');
            Role::query()->where('id', $role_id)->delete();
            \DB::table('role_has_roles')->where('role_id', $role_id)->orWhere('child_id', $role_id)->delete();
            \DB::table('role_has_permissions')->where('role_id', $role_id)->delete();
            return ['data' => 'success'];
        });
    }
}
