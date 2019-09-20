<?php


namespace YiluTech\Permission\Commands;


use YiluTech\Permission\PermissionDBSync;

class PermissionRecordCommand extends BasePermissionCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:record {--path=} {--merge} {--db} {--auth=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'record permission.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('db')) {
            if (count($changes = $this->getChanged())) {
                (new PermissionDBSync($this->option('auth')))->record($changes);
            }
        } else {
            $old = $this->option('merge') ? $this->getLastStored() : $this->getStored();
            $new = $this->getRoutePermission();

            $changes = $this->getChanges($new, $old);
            $this->syncFile($old, $changes);
        }

        $this->info('record ' . count($changes) . ' changes.');
    }

    protected function syncFile($old, $changes)
    {
        foreach ($changes as $item) {
            if ($item['action'] === 'delete') {
                unset($old[$item['name']]);
            } else {
                unset($item['action'], $item['changes']);
                $old[$item['name']] = $item;
            }
        }
        $this->write($this->getPath('permissions.php'), $old);
        $this->write($this->getPath('changes.php'), $changes);
    }
}
