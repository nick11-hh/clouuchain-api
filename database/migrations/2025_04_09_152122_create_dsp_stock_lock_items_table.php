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
        Schema::create('dsp_stock_lock_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('lock_id')->comment('锁定id');
            $table->bigInteger('stock_item_id')->comment('锁定的库存item id');
            $table->bigInteger('location_id')->comment('库位id');
            $table->string('location_code')->comment('库位code');
            $table->integer('quantity')->comment('锁定数量');
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
        Schema::dropIfExists('dsp_stock_lock_items');
    }
};
