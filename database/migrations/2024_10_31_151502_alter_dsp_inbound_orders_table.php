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
        Schema::table('dsp_inbound_orders', function (Blueprint $table) {
            $table->tinyInteger('order_source')->default(1)->comment('入库单来源 1系统添加 2客户下单')->after('inbound_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_inbound_orders', function (Blueprint $table) {
            $table->dropColumn('order_source');

        });
    }
};
