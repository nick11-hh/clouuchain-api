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
        Schema::table('dsp_shop_order_fulfillments', function (Blueprint $table) {
            $table->string('tracking_company')->nullable()->comment('物流公司');
            $table->string('fulfillment_service')->nullable()->comment('履约服务商');
            $table->timestamp('fulfillment_at')->nullable()->comment('履约创建时间');
            $table->dropColumn('tracking_notes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_fulfillments', function (Blueprint $table) {
            $table->dropColumn('tracking_company');
            $table->dropColumn('fulfillment_service');
            $table->dropColumn('fulfillment_at');
            $table->string('tracking_notes')->nullable()->comment('物流备注');
        });
    }
};
