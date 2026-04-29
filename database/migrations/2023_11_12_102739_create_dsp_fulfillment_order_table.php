<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('dsp_fulfillment_order', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('order_id')->default(0)->comment('订单id');
            $table->bigInteger('fulfillment_order_id')->default(0)->comment('履行订单id');
            $table->bigInteger('fulfillment_order_line_item_id')->default(0)->comment('履行订单项id');
            $table->bigInteger('line_item_id')->default(0)->comment('订单项id');
            $table->string('request_status')->default('')->comment('履行订单状态');
            $table->string('status')->default('')->comment('订单状态');
            $table->json('fulfillment')->nullable()->comment('履行订单信息');
            $table->index('order_id');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement("alter table `dsp_fulfillment_order` comment '履行订单' ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_fulfillment_order');
    }
};
