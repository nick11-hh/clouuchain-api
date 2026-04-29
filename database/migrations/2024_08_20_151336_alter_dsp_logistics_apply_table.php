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
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->bigInteger('express_order_id')->default(0)->index()->comment('物流订单ID');
            $table->string('package_sn')->nullable()->comment('物流订单包裹号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->dropColumn(['express_order_id', 'package_sn']);
        });
    }
};
