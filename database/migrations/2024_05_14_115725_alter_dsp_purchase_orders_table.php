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
            $table->timestamp('delivered_time')->nullable()->comment('发货时间');
            $table->timestamp('pay_time')->nullable()->comment('付款时间');
            $table->string('logistics_company_name')->default('')->comment('物流商名称');
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
            $table->dropColumn(['delivered_time', 'pay_time', 'logistics_company_name']);
        });
    }
};
