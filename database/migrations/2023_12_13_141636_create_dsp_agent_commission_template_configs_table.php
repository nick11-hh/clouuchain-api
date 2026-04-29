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
        Schema::create('dsp_agent_commission_template_configs', function (Blueprint $table) {
            $table->comment('代理佣金模板多级配置表');
            $table->bigIncrements('id');
            $table->bigInteger('template_id')->comment('模板ID');
            $table->bigInteger('level')->default(1)->comment('代理等级');
            $table->unsignedTinyInteger('type')->default(1)->comment('计算方式');
            $table->bigInteger('value')->comment('值');
            $table->unsignedTinyInteger('mode')->comment('计佣金额模式');
            $table->bigInteger('first_order_value')->nullable()->comment('客户首单佣金(固定金额)');
            $table->index('template_id');
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
        Schema::dropIfExists('dsp_agent_commission_template_configs');
    }
};
