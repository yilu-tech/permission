<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\StoreManager;

class GeneratePermissionMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:ps-migration {--type=json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate permissions.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (!in_array($this->option('type'), ['json', 'yaml'])) {
            $this->error(sprintf('invalid type.'));
            return;
        }
        if ($this->option('type') === 'yaml' && !function_exists('yaml_emit_file')) {
            $this->error('yaml extension not support.');
            return;
        }

        $path = base_path(config('permission.migration_path', 'database/permission'));

        $manager = new StoreManager(config('permission'));
        foreach ($manager->stores() as $name => $store) {
            $this->generate($path, $store);
        }
        $this->info('generate success.');
    }

    public function generate($directory, $store)
    {
        $prefix = date('Y_m_d_His');
        if ($name = $store->name()) {
            $prefix .= '_' . $name;
        }
        $type = $this->option('type');
        $path = $directory . DIRECTORY_SEPARATOR . $prefix . '.' . $type;
        if (file_exists($path)) {
            $this->error(sprintf('file %s exists.', $path));
            return;
        }

        if (empty($items = $store->items())) {
            $this->info(sprintf('store[%s] nothing generate.', $name ?: 'default'));
        } else {
            call_user_func([$this, 'write' . ucfirst($type)], $path, $items);
            $this->info(sprintf('store[%s] generate to file %s.', $name ?: 'default', $path));
        }
    }

    protected function writeJson($path, $content)
    {
        file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT));
    }

    protected function writeYaml($path, $content)
    {
        yaml_emit_file($path, $content);
    }
}
