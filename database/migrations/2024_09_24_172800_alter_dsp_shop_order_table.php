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
            $table->decimal('refund_price')->default(0)->comment('退款金额');
            $table->tinyInteger('financial_status')->default(0)->comment('财务状态 0-未支付 1-已支付 2-补收费用 3-部分退款 4-全额退款');
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
            $table->dropColumn(['financial_status', 'refund_price']);
        });
    }
};
