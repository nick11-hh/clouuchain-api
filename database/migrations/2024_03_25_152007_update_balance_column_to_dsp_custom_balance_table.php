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
        Schema::table('dsp_custom_balance', function (Blueprint $table) {
            $table->bigInteger('balance')->comment('余额(USD)')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_custom_balance', function (Blueprint $table) {
            $table->bigInteger('balance')->comment('余额（分）')->change();
        });
    }
};
