<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\PermissionManager;

class PermissionRollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:rollback {date} {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'rollback permission.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $manager = new PermissionManager();

        if ($path = $this->option('path')) {
            $manager->setFilePath($path);
        }

        $date = $this->argument('date');

        if (strtolower($date) === 'null') {
            $date = null;
        } elseif (strtotime($date) === false) {
            $this->info('Date format error.');
            return;
        }

        if ($count = count($manager->readFile($date))) {
            $manager->rollbackChanges($date);
            $this->info("Rollback $count changes.");
        } else {
            $this->info('Nothing to rollback.');
        }
    }
}
