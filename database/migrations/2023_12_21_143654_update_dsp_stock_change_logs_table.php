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
        Schema::table('dsp_stock_change_logs', function (Blueprint $table) {
            $table->tinyText('sku_image')->after('sku')->nullable()->comment('sku 图片');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_stock_change_logs', function (Blueprint $table) {
            $table->dropColumn('sku_image');
        });
    }
};
