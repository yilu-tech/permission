<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:34
 */

namespace YiluTech\Permission\Controllers;

use Illuminate\Validation\Rule;
use YiluTech\Permission\Helper\RoleGroup;
use YiluTech\Permission\Models\Role;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\RedisStore;

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
            'status' => 'array',
            'status.*' => [Rule::in([RS_EXTEND, RS_WRITE, RS_BASIC])],
            'roles' => 'array',
            'permissions' => 'array'
        ]);
        $data = \Request::only(['name', 'description', 'config', 'roles', 'permissions', 'status']);
        $data['group'] = $group;
        $data['status'] = array_reduce($data['status'] ?? [], function ($mask, $status) {
            return $mask | $status;
        }, RS_READ);

        return \DB::transaction(function () use ($data) {
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
        if (!$role) throw new PermissionException('Role not exists.');

        if (!($role->status & RS_WRITE)) {
            throw new PermissionException('Role not allow edit.');
        }

        $data = \Request::only(['name', 'description', 'config', 'roles', 'permissions']);

        if ($role->status & RS_SYS && !empty($data['roles'])) {
            throw new PermissionException('System role can not extend other roles.');
        }

        return \DB::transaction(function () use ($role, $data) {
            $changes = $role->syncChildRoles($data['roles'] ?? []) + $role->syncPermissions($data['permissions'] ?? []);
            $role->update($data);

            if ($changes) {
                resolve(RedisStore::class)->empty($role);
            }
            return $role;
        });
    }

    public function delete()
    {
        \Request::validate(['role_id' => 'required|integer']);

        $role = Role::findById(\Request::input('role_id'), RoleGroup::getFromQuery());
        if (!$role) throw new PermissionException('Role not exists.');

        if ($role->status & RS_SYS) {
            throw new PermissionException('Can not remove system role.');
        }

        if (\DB::table('user_has_roles')->where('role_id', $role->id)->exists()) {
            throw new PermissionException('Can not remove role, role used.');
        }

        return \DB::transaction(function () use ($role) {
            $role->permissionRelation()->detach();
            $role->childRoleRelation()->detach();

            \DB::table('user_has_roles')->where('role_id', $role->id)->delete();
            $role->delete();

            resolve(RedisStore::class)->empty($role);
            return ['data' => 'success'];
        });
    }
}
