<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use YiluTech\Permission\MigrationBatch;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\StoreManager;

class MigratePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:migrate {--t|test=N : 测试migration文件生成差异信息, 逆向读取 N 个测试文件} {--d|db : 从数据库比对进行测试}';

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
        try {
            $manager = new StoreManager(config('permission'));
            $manager->loadMigrations();
            if ($this->option('test') !== 'N') {
                $formatter = $this->output->getFormatter();
                $formatter->setStyle('red', new OutputFormatterStyle('red'));

                if ($this->option('db')) {
                    $this->testFromDb($manager);
                } else {
                    $this->testFromFile($manager);
                }

                $this->info('test finished.');
            } else {
                $this->migrate($manager);
            }
        } catch (PermissionException $exception) {
            $this->error($exception->getMessage());
        }
    }

    protected function migrate($manager)
    {
        $count = 0;
        foreach ($manager->stores() as $store) {
            $this->info(sprintf('migrating store[%s]', $store->name() ?: 'default'));
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

    protected function testFromDb($manager)
    {
        foreach ($manager->stores() as $store) {
            $this->info(sprintf('testing store[%s] migrations', $store->name() ?: 'default'));
            [$files, $changes] = $store->getChanges();

            if (empty($files)) {
                $this->info('no file to test.');
                continue;
            }

            $this->line(sprintf('files: <info>%d</info>', count($files)));
            $this->info(implode("\n", $files));
            $this->renderChanges($changes);
        }
    }

    /**
     * @param StoreManager $manager
     */
    protected function testFromFile($manager)
    {
        $files = $this->option('test') ?: 1;
        foreach ($manager->stores() as $store) {
            $this->info(sprintf('testing store[%s] migrations', $store->name() ?: 'default'));
            $didMigrations = array_slice($store->getMigrations(), 0, -$files);
            $undoMigrations = array_slice($store->getMigrations(), -$files);

            if (empty($undoMigrations)) {
                $this->info('no file to test.');
                continue;
            }
            $this->line(sprintf('files: <info>%d</info>', count($undoMigrations)));
            $this->info(implode("\n", array_keys($undoMigrations)));

            $collection = (new MigrationBatch($didMigrations))->toCollection();
            $batch = new MigrationBatch($undoMigrations);
            $this->renderChanges($batch->getChanges($collection));
        }
    }

    protected function renderChanges($changes)
    {
        if (empty($changes)) {
            $this->warn('file no changes to do.');
            return;
        }

        $this->line(sprintf('changes: <info>%d</info>', count($changes)));
        foreach ($changes as $name => [$action, $change]) {
            if ($action === 'delete') {
                $this->line(sprintf('<red>- %s</red>', $name));
            } else if ($action === 'create') {
                $this->line(sprintf('<info>+ %s</info>', $name));
            } else {
                if (isset($change['name'])) {
                    $this->line(sprintf('<red>  %s</red> => <info>%s</info>', $name, $change['name']));
                } else {
                    $this->line(sprintf('<info>  %s</info>', $name));
                }
            }
        }
    }
}
