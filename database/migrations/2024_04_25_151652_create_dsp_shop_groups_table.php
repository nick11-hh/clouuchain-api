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
        Schema::create('dsp_shop_groups', function (Blueprint $table) {
            $table->id();
            $table->integer('admin_id')->default(0)->comment('管理员ID')->index();
            $table->string('group_name')->nullable()->comment('分组名称');
            $table->string('description')->nullable()->comment('分组描述');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('店铺分组表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_groups');
    }
};
