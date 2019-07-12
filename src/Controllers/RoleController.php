<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:34
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\IdentityUtil;
use YiluTech\Permission\Models\Role;

class RoleController
{
    public function list()
    {
        $query = Role::query()->leftJoin('role_has_roles', 'role_has_roles.role_id', 'roles.id')
            ->select('roles.*', \DB::raw('group_concat(child_id separator ",") as child_keys'))->groupBy('id');

        if ($identifier = IdentityUtil::getIdentifier()) {
            IdentityUtil::whereIdentity($query, $identifier, true, 'roles');
        }

        return $query->get()->each(function ($item) {
            if ($item->child_keys) {
                $item->child_keys = explode(',', $item->child_keys);
            }
        });
    }

    public function create()
    {
        \Request::validate([
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}]{2,16}$/u'],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'identifier' => 'nullable|array|min:1',
            'roles' => 'required|array',
            'permissions' => 'required|array'
        ]);

        return \DB::transaction(function () {
            $data = \Request::only(['name', 'description', 'config']);
            $data['child_length'] = count($roles = \Request::input('roles', []));
            $role = Role::create($data, \Request::input('identifier'));

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
            'name' => ['required', 'regex:/^[A-Za-z0-9\x{4e00}-\x{9fa5}]{2,16}$/u'],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'roles' => 'required|array',
            'permissions' => 'required|array'
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

    }
}
