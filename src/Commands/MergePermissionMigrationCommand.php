<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\StoreManager;

class MergePermissionMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:merge {--yaml : 生成yaml格式文件,需要php yaml扩展支持}';

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
        if ($this->option('yaml') && !function_exists('yaml_emit_file')) {
            $this->error('yaml extension not support.');
            return;
        }

        try {
            $path = base_path(config('permission.migration_path', 'database/permission'));
            $manager = new StoreManager(config('permission'));
            foreach ($manager->stores() as $name => $store) {
                $this->merge($path, $store);
            }
            $this->info('merge finished.');
        } catch (PermissionException $exception) {
            $this->error($exception->getMessage());
        }
    }

    public function merge($directory, $store)
    {
        $prefix = date('Y_m_d_His');
        if ($name = $store->name()) {
            $prefix .= '_' . $name;
        }
        $type = $this->option('yaml') ? 'yaml' : 'json';

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
            $this->info(sprintf('store[%s]', $name ?: 'default'));
            $this->info(sprintf('generate file %s.', $path));

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
        file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected function writeYaml($path, $content)
    {
        yaml_emit_file($path, $content, YAML_UTF8_ENCODING);
    }
}
