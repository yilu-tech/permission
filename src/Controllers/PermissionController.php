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
            'action' => 'required|in:getMigrated,getItems,getChanges,migrate,rollback,mergeTo',
            'service' => 'required|string'
        ]);
        try {
            return [
                'code' => 'success',
                'data' => call_user_func([$this, \Request::input('action')], \Request::input('service'))
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 'fail',
                'message' => $exception->getMessage()
            ];
        }
    }

    protected function getItems($service)
    {
        return app(LocalStore::class, ['service' => $service])->items();
    }

    protected function mergeTo($service)
    {
        \Request::validate([
            'file' => 'required|string',
        ]);
        return app(LocalStore::class, ['service' => $service])->mergeTo(\Request::input('file'));
    }

    protected function getMigrated($service)
    {
        \Request::validate([
            'steps' => 'integer',
        ]);
        return app(Migration::class, ['service' => $service])->migrated(\Request::input('steps', 1));
    }

    protected function migrate($service)
    {
        \Request::validate([
            'migrations' => 'array|min:1',
            'migrations.*' => 'file',
        ]);
        $batch = app(MigrationBatch::class, ['files' => \Request::file('migrations')]);
        $batch->migrate($service);
    }

    protected function getChanges($service)
    {
        \Request::validate([
            'migrations' => 'array|min:1',
            'migrations.*' => 'file',
        ]);
        $batch = app(MigrationBatch::class, ['files' => \Request::file('migrations')]);
        return $batch->getChanges($service);
    }

    protected function rollback($service)
    {
        \Request::validate([
            'steps' => 'integer|min:1',
        ]);
        return app(LocalStore::class, ['service' => $service])->rollback(\Request::input('steps', 1));
    }
}
