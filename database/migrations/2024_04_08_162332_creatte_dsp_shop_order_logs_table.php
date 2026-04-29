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
        Schema::create('dsp_shop_order_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->default(0)->comment('订单ID')->index();
            $table->integer('operator_type')->default(1)->comment('操作类型(1-报价 2-改价)');
            $table->text('content')->nullable()->comment('操作内容');
            $table->bigInteger('operator_id')->default(0)->comment('操作人ID');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('订单日志表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_logs');
    }
};
