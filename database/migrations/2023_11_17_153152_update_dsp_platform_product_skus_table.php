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
        Schema::table('dsp_platform_product_skus', function (Blueprint $table) {
            $table->decimal('compare_at_price')->nullable()->comment('比较价格');
            $table->bigInteger('inventory_item_id')->nullable()->comment('库存项id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_platform_product_skus', function (Blueprint $table) {
            //
        });
    }
};
