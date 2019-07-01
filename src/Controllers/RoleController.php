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
    public function create()
    {
        \Request::validate([
            'name' => ['required', 'regex:' . sprintf(REGEX_EN_ZH_CN, '{2,16}')],
            'description' => 'nullable|string|max:255',
            'config' => 'nullable',
            'roles' => 'required|array',
            'permissions' => 'required|array'
        ]);

        return \DB::transaction(function () {
            $data = \Request::only(['name', 'description', 'config']);
            $data['child_length'] = count($roles = \Request::input('roles', []));

            $role = Role::create($data, ['instance' => 1, 'shop' => 2]);

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
            'name' => ['required', 'regex:' . sprintf(REGEX_EN_ZH_CN, '{2,16}')],
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

/*
 *
 *     Route::group(['prefix' => 'permission'], function () {
Route::post('create', 'PermissionController@create')->name('permission.create');
Route::post('update', 'PermissionController@update')->name('permission.update');
Route::post('delete', 'PermissionController@delete')->name('permission.delete');
Route::post('translate', 'PermissionController@translate')->name('permission.translate');
Route::post('removetranslation', 'PermissionController@removeTranslation')->name('permission.removetranslation');
});

Route::group(['prefix' => 'role'], function () {
Route::post('create', 'RoleController@create')->name('role.create');
Route::post('update', 'RoleController@update')->name('role.update');
Route::post('delete', 'RoleController@delete')->name('role.delete');
});
 */
