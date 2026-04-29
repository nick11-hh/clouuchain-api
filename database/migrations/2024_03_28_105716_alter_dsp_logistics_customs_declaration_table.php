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
        Schema::table('dsp_logistics_customs_declaration', function (Blueprint $table) {
            $table->bigInteger('goods_sku_id')->default(0)->comment('本地产品skuID dsp_goods_skus.id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_customs_declaration', function (Blueprint $table) {
            $table->dropColumn(['goods_sku_id']);
        });
    }
};
