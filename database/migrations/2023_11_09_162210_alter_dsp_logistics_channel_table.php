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
        Schema::table('dsp_logistics_channel', function (Blueprint $table) {
            $table->string('spec')->default('')->comment("面单规格");
            $table->tinyInteger('is_print_order_info')->default(0)->comment("打印订单详情：0-不打印 1-打印");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_channel', function (Blueprint $table) {
            $table->dropColumn('spec');
            $table->dropColumn('is_print_order_info');
        });
    }
};
