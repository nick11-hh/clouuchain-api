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
            $table->tinyInteger('tail_course')->default(0)->comment('是否有尾程单号：0-没有 1-有');
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
            $table->dropColumn('tail_course');
        });
    }
};
