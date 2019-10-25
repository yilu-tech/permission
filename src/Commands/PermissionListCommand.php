<?php


namespace YiluTech\Permission\Commands;


class PermissionListCommand extends BasePermissionCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:list {--stored} {--last} {--changes} {--changed} {--path=}';

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
        $headers = ['name', 'type', 'scopes', 'content'];

        $path = $this->option('path');

        if ($this->option('stored')) {
            return $this->outputTable($headers, $this->manager->getStored($path));
        }

        if ($this->option('last')) {
            return $this->outputTable($headers, $this->manager->getLastStored($path));
        }

        if ($this->option('changed')) {
            $changes = $this->manager->getStoredChanges($path);
            if ($this->manager->isSyncedChanges($changes)) {
                array_shift($changes);
            }
            $headers[] = 'actions';
            $headers[] = 'changes';
            return $this->outputTable($headers, $changes);
        }

        if ($this->option('changes')) {
            $headers[] = 'actions';
            $headers[] = 'changes';
            return $this->outputTable($headers, $this->manager->getChanges($this->manager->getStored($path)));
        }

        return $this->outputTable($headers, $this->manager->all());
    }

    protected function outputTable($headers, $rows)
    {
        $rows = array_map(function ($item) {
            $item['scopes'] = $this->arrayToString($item['scopes'], -1, '', '');
            $item['content'] = $this->arrayToString($item['content'], -1, '', '');
            if (isset($item['changes'])) {
                $item['changes'] = $this->arrayToString($item['changes'], -1, '', '');
            }
            return $item;
        }, $rows);
        $this->table($headers, $rows);
    }
}
