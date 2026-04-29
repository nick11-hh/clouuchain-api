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
        Schema::create('dsp_shopify_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('package_id')->comment('包裹id');
            $table->bigInteger('order_id')->comment('订单id');
            $table->string('fulfillment_id')->comment('履约单id');
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
        Schema::dropIfExists('dsp_shopify_fulfillments');
    }
};
