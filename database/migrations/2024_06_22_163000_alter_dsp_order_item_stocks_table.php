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
        Schema::table('dsp_order_item_stocks', function (Blueprint $table) {
            $table->integer('order_packing_materials_id')->default(0)->comment('订单包材ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_order_item_stocks', function (Blueprint $table) {
            $table->dropColumn([
                'order_packing_materials_id',
            ]);
        });
    }
};
