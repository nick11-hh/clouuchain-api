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
        Schema::table('dsp_customs_quote_config', function (Blueprint $table) {
            // 新增 product_quote_review_profit_rate 字段
            $table->decimal('product_quote_review_profit_rate', 10, 2)->nullable()->default(20)->comment('商品报价审核利润率');
            // 新增 freight_quote_review_profit_rate 字段
            $table->decimal('freight_quote_review_profit_rate', 10, 2)->nullable()->default(20)->comment('运费报价审核利润率');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_customs_quote_config', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn(['product_quote_review_profit_rate', 'freight_quote_review_profit_rate']);
        });
    }
};
