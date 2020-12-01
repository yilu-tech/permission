<?php


namespace YiluTech\Permission\Commands;

use Illuminate\Console\Command;

class MakePermissionMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:ps-migration {--name=} {--type=json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate permission migration file.';

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

        $path = base_path(config('permission.migration_path', 'database/permission'));

        $prefix = date('Y_m_d_His');

        if ($name = $this->option('name')) {
            $prefix .= '_' . $name;
        }

        $path = $path . DIRECTORY_SEPARATOR . $prefix . '.' . $this->option('type');
        if (file_exists($path)) {
            $this->error(sprintf('file %s exists.', $path));
            return;
        }
        $this->info($path);
        $this->info('generate file success.');
    }
}
