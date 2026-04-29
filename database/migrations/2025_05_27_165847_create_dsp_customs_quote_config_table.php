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
        Schema::create('dsp_customs_quote_config', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->nullable()->index('customer_id');
            $table->decimal('product_quote_default_profit_rate')->nullable()->comment('商品报价默认利润率');
            $table->decimal('product_quote_default_fixed_amount', 12)->nullable()->comment('商品报价默认固定金额');
            $table->decimal('product_quote_default_minimum_profit', 12)->nullable()->comment('商品报价默认最低利润');
            $table->tinyInteger('freight_quote_amount_type')->nullable()->comment('运费报价金额类型 1实际报价 2物流成本');
            $table->decimal('freight_quote_default_profit_rate')->nullable()->comment('运费报价默认利润率');
            $table->decimal('freight_quote_default_fixed_amount', 12)->nullable()->comment('运费报价默固定金额');
            $table->decimal('freight_quote_default_minimum_profit', 12)->nullable()->comment('运费报价默认最低利润');
            $table->bigInteger('admin_id')->default(0)->nullable()->comment('管理员id');
            $table->timestamps();
            $table->comment('客户报价配置表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_customs_quote_config');
    }
};
