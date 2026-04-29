<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 10)->default(0)->comment('采购价格');
            $table->decimal('profit', 10)->default(0)->comment('利润');
            $table->decimal('logistics_fee', 10)->default(0)->comment('物流费用');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_line_items', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_price',
                'profit',
                'logistics_fee',
            ]);
        });
    }
};
