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
        Schema::create('dsp_system_config_operate_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('admin_id')->default(0)->comment('操作人id');
            $table->json('content')->nullable()->comment('操作内容');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('系统配置操作日志表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_system_config_operate_logs');
    }
};
