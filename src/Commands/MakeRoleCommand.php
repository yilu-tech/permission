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
    protected $signature = 'make:role {name} {--a|admin} {--s|sys} {--e|extend} {--b|basics} {--r|read} {--g|group=} {--A|alias=}';

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
        $role['alias'] = $this->option('alias');
        $role['group'] = $this->option('group');
        $role['status'] = 0;

        if ($this->option('admin')) {
            if ($this->adminExists($role['group'])) {
                $this->info('make error: group admin exists.');
                return;
            }
            $role['status'] = $role['status'] | RS_ADMIN;
        }

        if ($this->option('sys')) {
            $role['status'] = $role['status'] | RS_SYS;
        }

        if ($this->option('extend')) {
            $role['status'] = $role['status'] | RS_EXTEND;
        }

        if ($this->option('basics')) {
            $role['status'] = $role['status'] | RS_BASICS;
        }

        if ($this->option('read')) {
            $role['status'] = $role['status'] | RS_READ;
        }

        $role = Role::create($role);
        $this->info("make success: role id {$role->id} .");
    }

    protected function adminExists($group = null)
    {
        return Role::query()->where('status', '&', RS_ADMIN)->where('group', $group)->exists();
    }
}
