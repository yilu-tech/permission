<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:migrate';

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


    protected function readMigrations()
    {


    }

    protected function writeMigration()
    {

    }
}
