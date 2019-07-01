<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRoleHasRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 64);

            foreach (config('permission.identity.names', []) as $index => $item) {
                $table->integer("scope_$index");
            }

            $table->string('config', 512)->nullable();
            $table->string('description')->nullable();

            if (config('permission.role.extend')) {
                $table->integer('child_length')->default(0);
            }

            $table->timestamps();
        });

        if (config('permission.role.extend')) {
            Schema::create('role_has_roles', function (Blueprint $table) {
                $table->unsignedInteger('role_id');
                $table->unsignedInteger('child_id');
                $table->primary(['role_id', 'child_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
        if (config('permission.role.extend')) {
            Schema::dropIfExists('role_has_roles');
        }
    }
}
