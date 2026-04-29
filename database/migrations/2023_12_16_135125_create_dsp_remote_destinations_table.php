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
        Schema::create('dsp_remote_destinations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('remote_type_id')->comment('偏远类型ID');
            $table->bigInteger('country_id')->comment('国家ID');
            $table->bigInteger('area_id')->nullable()->comment('区域ID');
            $table->bigInteger('sub_area_id')->nullable()->comment('子区域ID');
            $table->string('city', 191)->nullable()->default('')->comment('城市');
            $table->string('start_postcode', 191)->nullable()->default('')->comment('起始邮编');
            $table->string('end_postcode', 191)->nullable()->default('')->comment('终止邮编');
            $table->string('grade', 191)->nullable()->default('')->comment('等级:A,B,C');
            $table->tinyInteger('source')->nullable()->default(0)->comment('来源0-自定义1-系统内置');
            $table->bigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->string('operator_name', 191)->nullable()->default('')->comment('操作人名称');
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
        Schema::dropIfExists('dsp_remote_destinations');
    }
};
