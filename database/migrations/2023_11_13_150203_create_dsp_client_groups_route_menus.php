<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_client_groups_route_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('user_group_id')->comment('员工组id');
            $table->integer('route_menu_id')->comment('权限id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_client_groups_route_menus');
    }
};
