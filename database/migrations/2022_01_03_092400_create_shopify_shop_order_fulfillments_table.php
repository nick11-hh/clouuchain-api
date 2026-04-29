<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopifyShopOrderFulfillmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::create('dsp_shop_order_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_notes')->nullable();
            $table->unsignedBigInteger('fulfillment_shopify_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shopify_shop_order_fulfillments');
    }
}
