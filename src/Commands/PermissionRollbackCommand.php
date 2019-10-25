<?php


namespace YiluTech\Permission\Commands;


use YiluTech\Permission\PermissionDBSync;

class PermissionRollbackCommand extends BasePermissionCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:rollback {--db} {--path=} {--auth=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migrate permission.';

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

        if ($synced) {
            array_shift($changes);
        }

        if ($count = count($changes)) {
            if ($this->option('db')) {
                if (!$synced) {
                    return $this->info('changes no commit.');
                }
                (new PermissionDBSync)->rollback($changes);
                $this->writeToFile($this->manager->getChangesFilePath($path), $changes);
            } else {
                if ($synced) {
                    return $this->info('changes has been sync to database, please rollback with db.');
                }
                $changes = null;
                $this->write(null, $this->manager->getLastStored($path));
            }
            $this->info("rollback $count rows.");
        } else {
            $this->info('no permission to rollback.');
        }
    }
}
