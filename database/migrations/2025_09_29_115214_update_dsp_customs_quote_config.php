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
            // 修改 product_quote_default_profit_rate 字段默认值
            $table->decimal('product_quote_default_profit_rate', 10, 2)
                ->default(20)
                ->nullable(false) // 如果需要设置为非空，可以取消注释
                ->change();

            // 修改 freight_quote_default_profit_rate 字段默认值
            $table->decimal('freight_quote_default_profit_rate', 10, 2)
                ->default(20)
                ->nullable(false) // 如果需要设置为非空，可以取消注释
                ->change();
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
            // 回滚时恢复原来的默认值（NULL）
            $table->decimal('product_quote_default_profit_rate', 10, 2)
                ->default(null)
                ->nullable()
                ->change();

            $table->decimal('freight_quote_default_profit_rate', 10, 2)
                ->default(null)
                ->nullable()
                ->change();
        });
    }
};
