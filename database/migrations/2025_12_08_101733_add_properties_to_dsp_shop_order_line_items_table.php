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
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->json('properties')->nullable()->comment('平台自定义购买属性');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->dropColumn('properties');
        });
    }
};
