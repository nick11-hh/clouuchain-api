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
            $table->tinyInteger('check_auth')->default(0)->comment('是否有核账权限 0否 1是');
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
            $table->dropColumn(['check_auth']);
        });
    }
};
