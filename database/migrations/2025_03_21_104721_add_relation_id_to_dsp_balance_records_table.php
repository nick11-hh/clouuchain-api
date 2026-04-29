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
        Schema::table('dsp_balance_records', function (Blueprint $table) {
                
            $table->bigInteger('relation_id')->default(0)->comment('关联id')->index('relation_id')->after('after_change_balance');
            $table->unique('serial_no', 'serial_no_unique');
        });


        Schema::table('dsp_recharge_applies', function (Blueprint $table) {

            $table->unique('serial_no', 'serial_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_balance_records', function (Blueprint $table) {

            $table->dropColumn('relation_id');
            $table->dropUnique('serial_no_unique');
        });

        Schema::table('dsp_recharge_applies', function (Blueprint $table) {

            $table->dropUnique('serial_no_unique');
        });
    }
};
