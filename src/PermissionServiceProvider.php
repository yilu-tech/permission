<?php


namespace YiluTech\Permission;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        define('RS_ADMIN', 1);
        define('RS_BASICS', 2);
        define('RS_SYS', 4);
        define('RS_READ', 8);
        define('RS_EXTEND', 16);
        define('RS_EXTENDED', 32);
        define('RS_DISABLED', 64);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'permission-migrations');
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \YiluTech\Permission\Commands\MakeRoleCommand::class,
                \YiluTech\Permission\Commands\PermissionListCommand::class,
                \YiluTech\Permission\Commands\PermissionRecordCommand::class,
                \YiluTech\Permission\Commands\PermissionRollbackCommand::class,
            ]);
        }

        $this->registerRoute();

        $this->offerPublishing();
    }

    public function registerRoute()
    {
        $defaultOptions = [
            'namespace' => '\YiluTech\Permission\Controllers',
        ];

        $options = array_merge($defaultOptions, $this->app['config']['permission']['route_option'] ?? []);

        Route::group($options, function ($router) {
            Route::get('permission/list', 'PermissionController@list')->name('permission.list');
            Route::post('permission/create', 'PermissionController@create')->name('permission.create');
            Route::post('permission/update', 'PermissionController@update')->name('permission.update');
            Route::post('permission/delete', 'PermissionController@delete')->name('permission.delete');
            Route::post('permission/translate', 'PermissionController@translate')->name('permission.translate');
            Route::post('permission/removetranslate', 'PermissionController@removetranslate')->name('permission.removetranslate');
            Route::get('role/list', 'RoleController@list')->name('role.list');
            Route::post('role/create', 'RoleController@create')->name('role.create');
            Route::post('role/update', 'RoleController@update')->name('role.update');
            Route::post('role/delete', 'RoleController@delete')->name('role.delete');
        });

        Route::group($this->app['config']['permission']['internal_route_option'] ?? [], function ($router) {
            Route::post('permission/sync', function ($request) {
                return PermissionDBSync::runRequest($request);
            })->name('permission.sync');
        });
    }

    protected function offerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/permission.php' => config_path('permission.php'),
            ], 'permission-config');
        }
    }
}
