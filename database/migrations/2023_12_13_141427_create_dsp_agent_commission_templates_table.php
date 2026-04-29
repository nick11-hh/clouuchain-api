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
        Schema::create('dsp_agent_commission_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->comment('名称');
            $table->unsignedTinyInteger('type')->default(1)->comment('默认的类型 1 按比列 2 按固定金额 3 按计费重量单位价格');
            $table->bigInteger('value')->comment('默认的比例或者金额');
            $table->boolean('mode')->default(false)->comment('是否计算订单额外费用');
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
        Schema::dropIfExists('dsp_agent_commission_templates');
    }
};
