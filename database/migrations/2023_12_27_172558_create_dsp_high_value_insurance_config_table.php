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
        Schema::create('dsp_high_value_insurance_config', function (Blueprint $table) {
            $table->comment('高货值工单配置表');
            $table->bigIncrements('id');
            $table->integer('enabled')->default(0)->comment('0-关闭 1-开启');
            $table->decimal('value', 65)->default(0)->comment('自定义触发货值');
            $table->bigInteger('work_order_type_id')->nullable()->comment('工单类型id');
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
        Schema::dropIfExists('dsp_high_value_insurance_config');
    }
};
