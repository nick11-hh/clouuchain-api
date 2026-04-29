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
        Schema::create('dsp_admin_groups_route_menus', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_group_id')->default(0)->comment('员工组ID');
            $table->bigInteger('route_menu_id')->default(0)->comment('路由菜单ID');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_admin_groups_route_menus');
    }
};
