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
        Schema::create('dsp_reserved_order_number_item', function (Blueprint $table) {
            $table->comment('预留单号表');
            $table->bigIncrements('id');
            $table->integer('reserved_order_number_id')->default(0)->comment('预留单号主表id');
            $table->string('order_sn', 191)->default('')->index('order_sn')->comment('单号');
            $table->integer('express_company_id')->default(0)->comment('快递公司id');
            $table->tinyInteger('is_used')->default(0)->comment('使用状态：0-未使用 1-已使用');
            $table->tinyInteger('is_invalid')->default(0)->comment('作废状态：0-正常 1-已作废');
            $table->string('uuid', 50)->default('')->index('file_uuid')->comment('导出文件的uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['order_sn', 'express_company_id'], 'order_sn_express_company_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_reserved_order_number_item');
    }
};
