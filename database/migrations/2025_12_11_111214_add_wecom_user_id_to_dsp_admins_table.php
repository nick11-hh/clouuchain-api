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
        Schema::table('dsp_admins', function (Blueprint $table) {
            $table->string('wecom_user_id')->nullable()->comment('企业微信用户id')->after('invite_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_admins', function (Blueprint $table) {
            $table->dropColumn('wecom_user_id');
        });
    }
};
