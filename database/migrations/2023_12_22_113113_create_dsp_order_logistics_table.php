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
        Schema::create('dsp_order_logistics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('order_id')->index('dsp_order_logistics_order_id_index');
            $table->bigInteger('shipment_logistics_id')->nullable()->index('dsp_order_logistics_shipment_logistics_id_index')->comment('所属发货单');
            $table->json('context');
            $table->string('operator', 191)->default('System')->comment('操作人');
            $table->json('country_site')->nullable()->comment('物流轨迹所在国家站点信息');
            $table->integer('logistic_index')->nullable()->comment('使用物流模板是的序号');
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
        Schema::dropIfExists('dsp_order_logistics');
    }
};
