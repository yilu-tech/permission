<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:45
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\LocalStore;
use YiluTech\Permission\Migration;
use YiluTech\Permission\MigrationBatch;
use YiluTech\Permission\Models\Permission;
use YiluTech\Permission\Models\Role;
use YiluTech\Permission\PermissionException;

class PermissionController
{
    public function list()
    {
        if ($role_id = (int)\Request::input('role_id')) {
            $role = Role::findById($role_id);
            if (!$role) {
                throw new PermissionException('Role not exists.');
            }
            if (\Request::has('with_child')) {
                return $role->permissions();
            }
            return $role->permissions();
        }
        return Permission::query(\Request::input('scope', false))->get();
    }

    public function call()
    {
        \Request::validate([
            'action' => 'required|in:getItems,getMigrated,migrate,rollback',
            'service' => 'required|string'
        ]);
        try {
            return [
                'code' => 'success',
                'data' => call_user_func([$this, \Request::input('action')])
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 'fail',
                'message' => $exception->getMessage()
            ];
        }
    }

    protected function getItems()
    {
        return app(LocalStore::class, ['service' => \Request::input('service')])->items();
    }

    protected function getMigrated()
    {
        \Request::validate([
            'times' => 'integer',
        ]);
        $migration = app(Migration::class, ['service' => \Request::input('service')]);
        return $migration->migrated(\Request::input('times', 1));
    }

    protected function migrate()
    {
        \Request::validate([
            'migrations' => 'array|min:1',
            'migrations.*' => 'file|mimes:txt',
        ]);
        $batch = app(MigrationBatch::class, ['files' => \Request::file('migrations')]);
        $batch->migrate(\Request::input('service'));
    }

    protected function rollback()
    {
        return app(LocalStore::class, ['service' => \Request::input('service')])->rollback();
    }
}
