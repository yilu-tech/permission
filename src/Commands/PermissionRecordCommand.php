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
    protected $signature = 'permission:record {--db} {--path=} {--auth=}';

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
            $changes = $this->getChanged();
            if ($synced = $this->isSyncChanges($changes)) {
                return $this->info('changes has been sync.');
            }
            (new PermissionDBSync($this->option('auth')))->record($changes);

            array_unshift($changes, 'changed');
            $this->write($this->getPath('changes.php'), $changes);

        } else {
            $synced = $this->isSyncChanges($this->getChanged());

            $old = $synced ? $this->getStored() : $this->getLastStored();
            $new = $this->getRoutePermission();

            $changes = $this->getChanges($new, $old);
            $this->syncFile($old, $changes);
        }

        $count = $synced ? count($changes) - 1 : count($changes);
        $this->info('record ' . $count . ' changes.');
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
