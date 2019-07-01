<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;

class MakeMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:migration {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migrate permission.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

    }

    public function getMigrations()
    {
        $storage_path = $this->getStoragePath();
        if (!$storage_path) {
            return false;
        }
        $dh = opendir($storage_path);

        $migrations = array();
        while ($dir = readdir($dh)) {
            if (substr($dir, -4) !== '.php') {
                continue;
            }
            $path = $storage_path . '/' . $dir;
            if ($migration = $this->readMigration($path)) {
                $migrations[] = $this->readMigration($path);
            }
        }
        closedir($dh);
        return $migrations;
    }


    protected function readMigration(string $path)
    {
        $content = require($path);

        if (!is_array($content)) {
            return false;
        }

        return [];
    }

    protected function writeMigration()
    {


    }

    protected function getStoragePath()
    {
        $path = $this->option('path') ?: array_get(config('permission'), 'migration_path') ?: 'database/seeds';
        $path = app_path($path);
        if (!is_dir($path)) {
            $this->error('store path not found');
        }
        return $path;
    }
}
