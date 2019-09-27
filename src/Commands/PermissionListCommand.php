<?php


namespace YiluTech\Permission\Commands;


class PermissionListCommand extends BasePermissionCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:list {--stored} {--last} {--changes} {--changed} {--merge-changes} {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'show permission list.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('stored')) {
            return $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore'], $this->getStored());
        }

        if ($this->option('last')) {
            return $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore'], $this->getLastStored());
        }

        if ($this->option('changed')) {
            return $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore', 'action', 'changes'], $this->getChanged());
        }

        if ($this->option('changes')) {
            return $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore', 'action', 'changes'], $this->getChanges($this->getRoutePermission(), $this->getStored()));
        }

        if ($this->option('merge-changes')) {
            return $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore', 'action', 'changes'], $this->getChanges($this->getRoutePermission(), $this->getLastStored()));
        }

        $this->outputTable(['name', 'type', 'method', 'path', 'auth', 'rbac_ignore'], $this->getRoutePermission());
    }

    protected function outputTable($headers, $rows)
    {
        $rows = array_map(function ($item) {
            $item['rbac_ignore'] = $item['rbac_ignore'] ? 'true' : 'false';

            if (is_array($item['auth'])) {
                $item['auth'] = $this->arrayToString($item['auth'], -1, '', '');
            }

            if (isset($item['changes'])) {
                $item['changes'] = $this->arrayToString($item['changes'], -1, '', '');
            }

            return $item;
        }, $rows);
        $this->table($headers, $rows);
    }
}
