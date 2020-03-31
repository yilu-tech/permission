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
    protected $signature = 'permission:record {--auth=*} {--path=} {--not-ignore}';

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

        if ($count = $manager->record()) {
            $this->info("Write $count changes.");
        } else {
            $this->info('Nothing to write.');
        }
    }
}
