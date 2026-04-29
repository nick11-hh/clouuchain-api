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
        Schema::create('dsp_shop_order_tag_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('shop_order_id')->default(0)->comment('订单id');
            $table->integer('shop_order_tag_id')->default(0)->comment('订单标签id');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('订单标签映射表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_tag_mappings');
    }
};
