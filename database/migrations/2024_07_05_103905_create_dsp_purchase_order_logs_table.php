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
        Schema::create('dsp_purchase_order_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('purchase_id')->default(0)->comment('采购订单ID')->index();
            $table->integer('operator_type')->default(1)->comment('操作类型');
            $table->text('content')->nullable()->comment('操作内容');
            $table->bigInteger('operator_id')->default(0)->comment('操作人ID');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('采购订单日志表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_purchase_order_logs');
    }
};
