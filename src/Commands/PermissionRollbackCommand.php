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
        $changes = $this->getChanged();
        if ($synced = $this->isSyncChanges($changes)) {
            array_shift($changes);
        }

        if ($count = count($changes)) {
            if ($this->option('db')) {
                if (!$synced) {
                    return $this->info('changes no commit.');
                }
                (new PermissionDBSync($this->option('auth')))->rollback($changes);
            } else {
                if ($synced) {
                    return $this->info('changes has been sync to database, please rollback with db.');
                }
            }
            $this->fileRollback();
            $this->info("rollback $count rows.");
        } else {
            $this->info('no permission to rollback.');
        }
    }

    protected function fileRollback()
    {
        $this->write($this->getPath('permissions.php'), $this->getLastStored());
        $this->file->delete($this->getPath('changes.php'));
    }
}
