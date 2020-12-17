<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\StoreManager;

class MergePermissionMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:merge {--type=json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'merge permission migrations.';

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
            $this->merge($path, $store);
        }
        $this->info('merge finished.');
    }

    public function merge($directory, $store)
    {
        $prefix = date('Y_m_d_His');
        if ($name = $store->name()) {
            $prefix .= '_' . $name;
        }
        $type = $this->option('type');

        $filename = $prefix . '.' . $type;
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            $this->error(sprintf('file %s exists.', $path));
            return;
        }

        [$files, $items] = $store->mergeTo($filename);
        if (empty($items)) {
            $this->info(sprintf('store[%s] nothing to merge.', $name ?: 'default'));
        } else {
            call_user_func([$this, 'write' . ucfirst($type)], $path, $items);
            $this->info(sprintf('store[%s] generate to file %s.', $name ?: 'default', $path));

            foreach ($files as $file) {
                $path = $directory . DIRECTORY_SEPARATOR . $file;
                if (file_exists($path)) {
                    @unlink($path);
                    $this->warn('remove: ' . $path);
                } else {
                    $this->error($path . ' not found.');
                }
            }
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
