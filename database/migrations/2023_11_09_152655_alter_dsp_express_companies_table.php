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
        Schema::table('dsp_express_companies', function (Blueprint $table) {
            $table->tinyInteger('enable')->default(0)->comment("状态：0-未启用 1-启用");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_companies', function (Blueprint $table) {
            $table->dropColumn('enable');
        });
    }
};
