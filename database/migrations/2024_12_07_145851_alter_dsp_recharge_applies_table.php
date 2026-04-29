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
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->timestamp('check_time')->nullable()->comment('核验时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_recharge_applies', function (Blueprint $table) {
            $table->dropColumn(['check_time']);
        });
    }
};
