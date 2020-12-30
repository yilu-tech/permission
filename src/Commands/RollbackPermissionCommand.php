<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\StoreManager;

class RollbackPermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:rollback {--steps=1 : 回滚次数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rollback permissions.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $manager = new StoreManager(config('permission'));
            $count = 0;
            foreach ($manager->stores() as $name => $store) {
                $this->info(sprintf('rollback store[%s]', $name ?: 'default'));
                $migration = $store->rollback(intval($this->option('steps')));
                $count += count($migration);
                $this->info(implode("\n", $migration));
            }
            if ($count) {
                $this->info('rollback success.');
            } else {
                $this->info('nothing to rollback.');
            }
        } catch (PermissionException $exception) {
            $this->error($exception->getMessage());
        }
    }
}
