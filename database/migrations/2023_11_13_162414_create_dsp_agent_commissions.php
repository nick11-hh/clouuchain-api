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
        Schema::create('dsp_agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->integer('agent_id')->comment('代理id');
            $table->integer('custom_id')->comment('客户id');
            $table->string('order_number')->comment('佣金分成关联的单号');
            $table->decimal('order_amount')->comment('订单金额');
            $table->integer('proportion')->comment('佣金比列 %');
            $table->decimal('commission_amount')->comment('佣金金额');
            $table->tinyInteger('status')->default(1)->comment('状态  1 未提现 2 提现中 3 已提现');
            $table->integer('withdraw_id')->default(0)->comment('提现申请id');
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
        Schema::dropIfExists('dsp_agent_commissions');
    }
};
