<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use YiluTech\Permission\Models\Role;

class MakeRoleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:role {name} {--admin} {--group=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'make role.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $role['name'] = $this->argument('name');
        $role['group'] = $this->option('group');

        if ($this->option('admin')) {
            if ($this->adminExists($role['group'])) {
                $this->info('make error: group admin exists.');
                return;
            }
            $role['child_length'] = -1;
        }
        $role = Role::create($role);
        $this->info("make success: role id {$role->id} .");
    }

    protected function adminExists($group = null)
    {
        return Role::query()->where('child_length', -1)->where('group', $group)->exists();
    }
}
