<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;
use YiluTech\Permission\Models\Permission;

class PermissionDBSync
{
    protected $auth;

    public function __construct($auth)
    {
        $this->auth = $auth;
    }

    public static function runRequest($request)
    {
        $self = new static($request->input('auth'));
        $action = $request->input('action');

        return $self->{$action}($request->input('changes'));
    }

    public function record($changes)
    {
        if ($this->isClient()) {
            return $this->callRemote('record', $changes);
        }
        \DB::transaction(function () use ($changes) {
            $this->eachChanges($changes, function ($change, $groups) {
                if ($change['action'] === 'create') {
                    $this->createChange($change, $groups);
                } elseif ($change['action'] === 'delete') {
                    $this->deleteChange($change, $groups);
                } else {
                    $this->updateChange($change, $groups);
                }
            });
        });
    }

    public function rollback($changes)
    {
        if ($this->isClient()) {
            return $this->callRemote('rollback', $changes);
        }
        \DB::transaction(function () use ($changes) {
            $this->eachChanges($changes, function ($change, $groups) {
                if ($change['action'] === 'create') {
                    $this->deleteChange($change, $groups);
                } elseif ($change['action'] === 'delete') {
                    $this->createChange($change, $groups);
                } else {
                    $change = array_merge($change, $change['changes']);
                    $this->updateChange($change, $groups);
                }
            });
        });
    }

    protected function isClient()
    {
        return !!config('permission.remote');
    }

    protected function callRemote($action, $changes)
    {
        $params = compact('action', 'changes');
        $params['auth'] = $this->auth;

        return (new Client)->post(config('permission.remote'), [
            'json' => $params
        ])->getBody()->getContents();
    }

    protected function eachChanges($changes, $callback)
    {
        foreach ($changes as $change) {
            foreach ((array)$change['auth'] as $item) {
                $groups = explode(',', $item);
                $auth = array_shift($groups);
                if (!$this->auth || $auth == $this->auth) {
                    $callback($change, count($groups) ? $groups : [null]);
                }
            }
        }
    }

    protected function createChange($change, $groups)
    {
        foreach ($groups as $group) {
            Permission::create(array_merge(['name' => $change['name'], 'type' => $change['type'], 'group' => $group], $this->getPermissionData($change)));
        }
    }

    protected function updateChange($change, $groups)
    {
        $rows = Permission::query()->where('name', $change['name'])->get();
        foreach ($groups as $group) {
            if ($row = $rows->firstWhere('group', $group)) {
                $row->update($this->getPermissionData($change));
                $row->flag = true;
            } else {
                Permission::create(array_merge(['name' => $change['name'], 'type' => $change['type'], 'group' => $group], $this->getPermissionData($change)));
            }
        }
        foreach ($rows as $row) if (!$row->flag) $row->delete();
    }

    protected function deleteChange($change, $groups)
    {
        Permission::query()->where('name', $change['name'])->delete();
    }

    protected function getPermissionData($permission)
    {
        return [
            'content' => [
                'url' => $permission['path']
            ]
        ];
    }
}
