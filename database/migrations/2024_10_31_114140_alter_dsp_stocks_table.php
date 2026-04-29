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
        Schema::table('dsp_stocks', function (Blueprint $table) {
            $table->bigInteger('in_transit_quantity')->default(0)->comment('在途库存数量')->after('lock_quantity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_stocks', function (Blueprint $table) {
            $table->dropColumn(['in_transit_quantity']);
        });
    }
};
