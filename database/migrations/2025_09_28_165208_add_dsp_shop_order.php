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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            // 新增 quote_id 字段
            $table->integer('quote_id')->default(0)->comment('报价模板ID');
            // 新增 channel_name 字段
            $table->string('channel_name')->default('')->comment('渠道名称');
            // 新增 myLogisticsId 字段
            $table->integer('myLogisticsId')->default(0)->comment('马帮物流商ID');
            // 新增 myLogisticsChannelId 字段
            $table->integer('myLogisticsChannelId')->default(0)->comment('马帮物流渠道ID');
            // 新增 exchange_rates 字段
            $table->decimal('exchange_rates', 10, 4)->nullable()->default(0.0000)->comment('汇率');
            // 新增 freight_profit 字段
            $table->decimal('freight_profit', 10, 2)->nullable()->default(0.00)->comment('物流利润率');
            // 新增 product_profit 字段
            $table->decimal('product_profit', 10, 2)->nullable()->default(0.00)->comment('产品利润率');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn(['quote_id', 'channel_name', 'myLogisticsId', 'myLogisticsChannelId', 'exchange_rates', 'freight_profit', 'product_profit']);
        });
    }
};
