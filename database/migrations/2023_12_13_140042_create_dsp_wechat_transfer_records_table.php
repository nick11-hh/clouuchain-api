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
        Schema::create('dsp_wechat_transfer_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->bigInteger('record_id')->comment('记录ID');
            $table->unsignedTinyInteger('source')->comment('来源');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态');
            $table->string('out_batch_no', 191)->comment('外部订单号');
            $table->string('batch_id', 191)->comment('批次号');
            $table->json('data')->comment('返回数据');
            $table->timestamps();
            $table->bigInteger('company_id');

            $table->index(['record_id', 'company_id'], 'jiyun_wechat_transfer_records_record_id_company_id_index');
            $table->index(['record_id', 'source'], 'jiyun_wechat_transfer_records_record_id_source_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_wechat_transfer_records');
    }
};
