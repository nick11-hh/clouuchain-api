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
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->string('phone_area_code', 16)->default('')->comment('手机区号')->after('custom_phone');
            $table->string('default_language', 16)->comment('默认语言')->default('zh_CN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_customs', function (Blueprint $table) {
            $table->dropColumn('phone_area_code');
            $table->dropColumn('default_language');
        });
    }
};
