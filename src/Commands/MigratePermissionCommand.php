<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\StoreManager;

class MigratePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migrate permissions.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $manager = new StoreManager(config('permission'));
        $manager->loadMigrations();

        $count = 0;
        foreach ($manager->stores() as $name => $store) {
            $this->info(sprintf('migrating store[%s]', $name ?: 'default'));
            $migration = $store->migrate();
            $count += count($migration);
            $this->info(implode("\n", $migration));
        }
        if ($count) {
            $this->info('migrate success.');
        } else {
            $this->info('nothing to migrate.');
        }
    }
}
