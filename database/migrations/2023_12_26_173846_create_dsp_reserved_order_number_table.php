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
        Schema::create('dsp_reserved_order_number', function (Blueprint $table) {
            $table->comment('预留单号主表');
            $table->bigIncrements('id');
            $table->string('batch', 191)->default('')->comment('批次');
            $table->integer('express_company_id')->default(0)->comment('快递公司id');
            $table->string('remark', 191)->default('')->comment('备注');
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
        Schema::dropIfExists('dsp_reserved_order_number');
    }
};
