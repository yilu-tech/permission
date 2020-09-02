<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\PermissionManager;

class PermissionDiffCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:diff';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'diff permissions.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $manager = new PermissionManager();
        $changes = $manager->getChanges();
        dump($changes);
//        foreach ($changes as $change) {
//            $this->info(sprintf('server: %s', $change['server']));
//            foreach ($change['changes'] as $item) {
//                if ($item['action'] === 'create') {
//                    echo "\e[0;32m+ \e[0m{$item['data']['name']} \n";
//                }
//                if ($item['action'] === 'update') {
//                    echo "  {$item['name']} <<< \n";
//                    $origin = json_encode($item['origin']);
//                    $change = json_encode($item['data']);
//                    echo "\e[0;31m- \e[0m$origin \n";
//                    echo "\e[0;32m+ \e[0m$change \n";
//                    echo ">>> \n";
//                }
//                if ($item['action'] === 'delete') {
//                    echo "\e[0;31m- \e[0m{$item['name']} \n";
//                }
//            }
//        }
    }
}
