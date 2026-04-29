<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->decimal('max_purchase_price', 10)->nullable()->comment('最大采购金额');
            $table->decimal('min_purchase_price', 10)->nullable()->comment('最小采购金额');
            $table->decimal('max_sale_price', 10)->nullable()->comment('最大销售金额');
            $table->decimal('min_sale_price', 10)->nullable()->comment('最小销售金额');
        });

        DB::update("
            update 
                dsp_goods 
            set 
                max_purchase_price = (select max(purchase_price) from  dsp_goods_skus where dsp_goods_skus.goods_id = dsp_goods.id),
                min_purchase_price = (select min(purchase_price) from  dsp_goods_skus where dsp_goods_skus.goods_id = dsp_goods.id),
                max_sale_price = (select max(sale_price) from  dsp_goods_skus where dsp_goods_skus.goods_id = dsp_goods.id),
                min_sale_price = (select min(sale_price) from  dsp_goods_skus where dsp_goods_skus.goods_id = dsp_goods.id)
            ");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->dropColumn('max_purchase_price');
            $table->dropColumn('min_purchase_price');
            $table->dropColumn('max_sale_price');
            $table->dropColumn('min_sale_price');
        });
    }
};
