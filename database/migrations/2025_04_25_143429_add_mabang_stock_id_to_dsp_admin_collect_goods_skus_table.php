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
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->bigInteger('mabang_stock_id')->nullable()->default(0)->comment('马帮Erp商品编号')->index('mabang_stock_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->dropColumn('mabang_stock_id');
        });
    }
};
