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
        Schema::create('dps_order_item_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->comment('平台');
            $table->string('platform_variant_id')->comment('平台产品sku_id');
            $table->string('goods_sku_id')->comment('本地产品sku_id');
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
        Schema::dropIfExists('dps_order_item_mappings');
    }
};
