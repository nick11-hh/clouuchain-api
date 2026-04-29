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
        Schema::table('dsp_platform_products', function (Blueprint $table) {
            $table->string('product_id')->change();
        });
        Schema::table('dsp_platform_product_skus', function (Blueprint $table) {
            $table->string('platform_sku_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_platform_products', function (Blueprint $table) {
            $table->bigInteger('product_id');
        });
        Schema::table('dsp_platform_product_skus', function (Blueprint $table) {
            $table->bigInteger('platform_sku_id');
        });
    }
};
