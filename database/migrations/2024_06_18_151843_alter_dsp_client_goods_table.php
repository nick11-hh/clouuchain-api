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
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->tinyInteger('goods_type')->default(1)->comment('商品类型 1-产品 2-包材');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->dropColumn(['goods_type']);
        });
    }
};
