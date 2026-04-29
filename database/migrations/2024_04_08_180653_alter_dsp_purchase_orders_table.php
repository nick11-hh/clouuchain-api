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
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->tinyInteger('transaction_method')->default(0)->comment('交易方式: 0-支付宝 1-线下');
            $table->integer('purchase_account_id')->default(0)->comment('采购账号id');
            $table->timestamp('expect_time')->nullable()->comment('预计到货时间');
            $table->decimal('other_fees', 20)->default(0)->comment('其他费用');
            $table->decimal('freight', 20)->default(0)->comment('运费');
            $table->text('message')->nullable()->comment('给卖家留言');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['transaction_method', 'purchase_account_id', 'expect_time', 'other_fees', 'freight', 'message']);
        });
    }
};
