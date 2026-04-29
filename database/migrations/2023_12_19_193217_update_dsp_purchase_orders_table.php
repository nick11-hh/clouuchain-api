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
            $table->bigInteger('warehouse_id')->default(0)->comment('仓库id');
            $table->bigInteger('custom_id')->default(0)->comment('仓库id');
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
            $table->dropColumn('warehouse_id');
            $table->dropColumn('custom_id');
        });
    }
};
