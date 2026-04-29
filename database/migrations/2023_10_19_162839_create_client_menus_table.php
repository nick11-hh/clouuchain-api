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
        Schema::create('dsp_client_menus', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->string('menu_name')->comment('菜单名称');
            $table->string('menu_code')->comment('菜单code编码');
            $table->string('parent_id')->default(0)->comment('父级id');
            $table->string('enabled')->default(1)->comment('是否可用');
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
