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
    protected $signature = 'permission:record {--db} {--path=}';

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
        $path = $this->option('path');

        $changes = $this->manager->getStoredChanges($path);
        $synced = $this->manager->isSyncedChanges($changes);

        if ($this->option('db')) {
            if ($synced) {
                return $this->info('changes has been sync.');
            }
            if (!count($changes)) {
                return $this->info('no changes to sync.');
            }
            (new PermissionDBSync)->record($changes);

            array_unshift($changes, 'changed');
            $this->writeToFile($this->manager->getChangesFilePath($path), $changes);
            $synced = true;
        } else {
            $old = $synced ? $this->manager->getStored($path) : $this->manager->getLastStored($path);
            $changes = $this->manager->getChanges($old);
            $this->write($changes);
        }
        $count = $synced ? count($changes) - 1 : count($changes);
        $this->info('record ' . $count . ' changes.');
    }
}
