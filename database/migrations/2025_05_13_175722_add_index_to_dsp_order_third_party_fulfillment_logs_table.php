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
            $table->index('order_id');
            $table->index('platform_order_no');
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
            $table->dropIndex('order_id');
            $table->dropIndex('platform_order_no');
        });
    }
};
