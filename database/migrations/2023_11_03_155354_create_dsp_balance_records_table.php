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
        Schema::create('dsp_balance_records', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id')->index();
            $table->tinyInteger('type')->comment('收入或者支出,收入为 1 支出为 2')->index();
            $table->integer('source_type')->comment('操作来源')->index();
            $table->bigInteger('amount')->comment('金额（分）');
            $table->string('order_sn')->comment('相关订单号')->index();
            $table->string('serial_no')->comment('流水号')->index();
            $table->string('out_serial_no')->comment('外部流水号');
            $table->text('remark')->nullable()->comment('备注');
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
        Schema::dropIfExists('dsp_balance_records');
    }
};
