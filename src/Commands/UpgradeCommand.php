<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use YiluTech\Permission\Models\Role;

class UpgradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:upgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'upgrade.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->updateTable();
        $this->info('upgrade success');
    }

    protected function updateTable()
    {
        if (!Schema::hasColumn('permissions', 'version')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('translations');
            });

            $this->info('add permission version column');
        }

        Schema::dropIfExists('permission_logs');
        Schema::create('permission_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('service', 32);
            $table->string('name', 128)->index();
            $table->text('content');
            $table->unsignedInteger('version');
            $table->dateTime('updated_at');
        });
        $this->info('create permission_logs table');
    }
}
