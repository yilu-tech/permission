<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\StoreManager;

class RollbackPermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rbac:rollback';

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
        $manager = new StoreManager(config('permission'));

        $count = 0;
        foreach ($manager->stores() as $name => $store) {
            $this->info(sprintf('rollback store[%s]', $name ?: 'default'));
            $migration = $store->rollback();
            $count += count($migration);
            $this->info(implode("\n", $migration));
        }
        if ($count) {
            $this->info('rollback success.');
        } else {
            $this->info('nothing to rollback.');
        }
    }
}
