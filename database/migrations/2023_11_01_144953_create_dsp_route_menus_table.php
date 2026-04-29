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
        Schema::create('dsp_route_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('菜单名称');
            $table->bigInteger('tag')->default(0)->comment('菜单标签');
            $table->string('route_method')->default('')->comment('路由方法');
            $table->string('route_path')->default('')->comment('路由路径');
            $table->string('route_name')->default('')->comment('路由名称');
            $table->bigInteger('parent_id')->default(0)->comment('父级ID');
            $table->tinyInteger('enabled')->default(1)->comment('是否启用');
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
        Schema::dropIfExists('dsp_route_menus');
    }
};
