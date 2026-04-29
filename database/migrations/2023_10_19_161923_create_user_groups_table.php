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
        Schema::create('dsp_user_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->string('group_name')->comment('分组名称');
            $table->string('description')->comment('分组描述');
            $table->json('menu_limit')->nullable()->comment('菜单权限');
            $table->tinyInteger('is_default')->default(0)->comment('是否为默认分组');
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
        Schema::dropIfExists('dsp_user_groups');
    }
};
