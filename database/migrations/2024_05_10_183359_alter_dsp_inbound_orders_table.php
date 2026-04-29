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
            $table->tinyInteger('inbound_type')->default(1)->comment('入库单类型: 1 备货入库  2 采购入库， 9 其他入库');
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
            $table->dropColumn('inbound_type');
        });
    }
};
