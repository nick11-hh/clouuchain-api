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
        Schema::table('dsp_order_third_party_fulfillment_logs', function (Blueprint $table) {
            $table->tinyInteger('type_id')->default(1)->nullable()->comment('类型 1订单 2产品')->index('type_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_order_third_party_fulfillment_logs', function (Blueprint $table) {
            $table->dropColumn('type_id');
        });
    }
};
