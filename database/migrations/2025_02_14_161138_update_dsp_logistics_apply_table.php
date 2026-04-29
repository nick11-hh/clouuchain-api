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
            $table->string('package_id')->comment('包裹id');
//            $table->dropColumn('order_id');
            $table->dropColumn('print');
            $table->dropColumn('express_order_id');
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
            $table->dropColumn('package_id');
//            $table->string('order_id');
            $table->integer('print');
            $table->bigInteger('express_order_id');
        });
    }
};
