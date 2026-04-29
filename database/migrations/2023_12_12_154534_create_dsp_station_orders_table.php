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
        Schema::create('dsp_station_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('status')->default(0);
            $table->bigInteger('station_id')->index('dsp_station_orders_station_id_index');
            $table->bigInteger('order_id')->index('dsp_station_orders_order_id_index');
            $table->string('order_sn', 191)->index('dsp_station_orders_order_sn_index');
            $table->string('location', 191)->nullable()->index('dsp_station_orders_location_index');
            $table->json('sign_images')->nullable()->comment('签收图片');
            $table->string('sign_remark', 512)->default('');
            $table->string('sign_signature', 191)->nullable()->comment('签收签名');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('shelved_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('pickup_code', 191)->nullable()->comment('取件码');
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
        Schema::dropIfExists('dsp_station_orders');
    }
};
