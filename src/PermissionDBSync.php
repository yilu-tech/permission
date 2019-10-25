<?php


namespace YiluTech\Permission;


use GuzzleHttp\Client;
use YiluTech\Permission\Models\Permission;

class PermissionDBSync
{
    public function record($changes)
    {
        if ($this->isClient()) {
            return $this->callRemote('record', $changes);
        }
        \DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                if ($change['action'] === 'create') {
                    $this->createChange($change);
                } elseif ($change['action'] === 'delete') {
                    $this->deleteChange($change);
                } else {
                    $this->updateChange($change);
                }
            }
        });
    }

    public function rollback($changes)
    {
        if ($this->isClient()) {
            return $this->callRemote('rollback', $changes);
        }
        \DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                if ($change['action'] === 'create') {
                    $this->deleteChange($change);
                } elseif ($change['action'] === 'delete') {
                    $this->createChange($change);
                } else {
                    $change = array_merge($change, $change['changes']);
                    $this->updateChange($change);
                }
            }
        });
    }

    protected function isClient()
    {
        return !!config('permission.remote');
    }

    protected function callRemote($action, $changes)
    {
        $url = config('permission.remote') . '/permission/sync';
        $params = compact('action', 'changes');

        return (new Client)->post($url, [
            'json' => $params,
        ])->getBody()->getContents();
    }

    protected function createChange($change)
    {
        Permission::create($change);
    }

    protected function updateChange($change)
    {
        Permission::query()->where('name', $change['name'])
            ->update([
                'type' => $change['type'],
                'scopes' => json_encode($change['scopes']),
                'content' => json_encode($change['content']),
            ]);
    }

    protected function deleteChange($change)
    {
        Permission::query()->where('name', $change['name'])->delete();
    }
}
