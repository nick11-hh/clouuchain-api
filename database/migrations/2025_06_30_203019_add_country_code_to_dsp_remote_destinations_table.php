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
        Schema::table('dsp_remote_destinations', function (Blueprint $table) {
            $table->string('country_code', 10)->nullable()->comment('国家编码')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_remote_destinations', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
