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
            $table->decimal('purchase_price', 10)->default(0)->comment('采购价格');
            $table->decimal('profit', 10)->default(0)->comment('利润');
            $table->decimal('quote_price', 10)->default(0)->comment('产品报价');
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
            $table->dropColumn([
                'purchase_price',
                'profit',
                'quote_price',
            ]);
        });
    }
};
