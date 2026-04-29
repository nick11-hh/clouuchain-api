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
        Schema::create('purchase_item_sources', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('purchase_item_id')->comment('采购单item id');
            $table->bigInteger('order_id')->comment('订单id');
            $table->bigInteger('order_item_id')->comment('订单item id');
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
        Schema::dropIfExists('purchase_item_sources');
    }
};
