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
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->tinyInteger('goods_once_price')->default(0)->comment('客户报价使用商品一口价');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->dropColumn('goods_once_price');
        });
    }
};
