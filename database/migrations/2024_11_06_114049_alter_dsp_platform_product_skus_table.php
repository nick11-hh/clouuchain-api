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
            $table->decimal('shipping_fee', 10, 2)->default(0)->comment('运费价格')->after('price');
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
            $table->dropColumn(['shipping_fee']);
        });
    }
};
