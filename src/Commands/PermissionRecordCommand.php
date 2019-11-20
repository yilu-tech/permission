<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\PermissionManager;

class PermissionRecordCommand extends Command
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
        $manager = new PermissionManager();

        if ($path = $this->option('path')) {
            $manager->setFilePath($path);
        }

        $this->option('db')
            ? $this->saveChanges($manager)
            : $this->writeChanges($manager);
    }

    protected function writeChanges(PermissionManager $manager)
    {
        $changes = $manager->getChanges($manager->old());
        if ($count = count($changes)) {
            $manager->writeFile($changes);
            $this->info("Write $count changes.");
        } else {
            $this->info('Nothing to write.');
        }
    }

    protected function saveChanges(PermissionManager $manager)
    {
        $changes = $manager->readFile($manager->getLastUpdateTime());
        if ($count = count($changes)) {
            $manager->writeDB($changes);
            $this->info("Save $count changes.");
        } else {
            $this->info('Nothing to save.');
        }
    }
}
