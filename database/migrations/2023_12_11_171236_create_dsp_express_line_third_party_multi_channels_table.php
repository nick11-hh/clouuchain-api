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
        Schema::create('dsp_express_line_third_party_multi_channels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->comment('路线ID');
            $table->bigInteger('first_num')->comment('第一个数值');
            $table->string('first_condition', 191)->comment('开始条件');
            $table->tinyInteger('type')->nullable()->default(1)->comment('类型1-订单计费重量2-订单实际重量');
            $table->string('second_condition', 191)->comment('结束条件');
            $table->bigInteger('second_num')->comment('第二个数值');
            $table->integer('docking_type')->comment('快递公司');
            $table->string('channel_code', 191)->nullable()->default('')->comment('渠道代码');
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
        Schema::dropIfExists('dsp_express_line_third_party_multi_channels');
    }
};
