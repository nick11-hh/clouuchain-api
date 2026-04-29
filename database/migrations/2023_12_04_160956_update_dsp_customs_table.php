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
            $table->string('custom_address')->after('custom_email')->nullable()->comment('客户地址信息');
        });
        Schema::table('dsp_users', function (Blueprint $table) {
            $table->string('avatar')->after('email')->nullable()->comment('用户头像');
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
            $table->dropColumn('custom_address');
        });
        Schema::table('dsp_users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
