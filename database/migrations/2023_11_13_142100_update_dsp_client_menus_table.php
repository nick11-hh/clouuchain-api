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
        Schema::dropIfExists('dsp_client_menus');
        Schema::create('dsp_client_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->string('name')->comment('菜单名称');
            $table->string('tag')->comment('菜单标签');
            $table->string('route_method')->nullable()->comment('路由方法');
            $table->string('route_path')->nullable()->comment('路由路径');
            $table->string('route_name')->nullable()->comment('路由名称');
            $table->integer('parent_id')->default(0)->comment('父级ID');
            $table->tinyInteger('enabled')->default(1)->comment('是否启用');
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
        Schema::dropIfExists('dsp_client_menus');
    }
};
