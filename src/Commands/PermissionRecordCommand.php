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

        $filter = null;
        if (!$this->option('not-ignore')) {
            $filter = function ($item) {
                return empty($item['content']['rbac_ignore']);
            };
        }
        if (count($auth = $this->option('auth'))) {
            $differ = function ($a, $b) {
                return ($a == $b || strpos($b, "$a.") === 0) ? 0 : 1;
            };
            $filter = function ($item) use ($differ, $auth, $filter) {
                return count(array_uintersect($auth, $item['scopes'], $differ)) && (!$filter || $filter($item));
            };
        }
        $manager->filter = $filter;

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
