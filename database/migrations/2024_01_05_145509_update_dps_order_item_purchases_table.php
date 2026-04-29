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
        Schema::table('dps_order_item_purchases', function (Blueprint $table) {
            $table->bigInteger('lock_id')->after('status')->comment('库存');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dps_order_item_purchases', function (Blueprint $table) {
            $table->dropColumn('lock_id');
        });
    }
};
