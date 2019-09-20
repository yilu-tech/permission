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
    protected $signature = 'permission:rollback {--path=} {--db}';

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
        if ($count = count($changes = $this->getChanged())) {
            if ($this->option('db')) {
                (new PermissionDBSync($this->option('auth')))->rollback($changes);
            } else {
                $this->fileRollback();
            }
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
