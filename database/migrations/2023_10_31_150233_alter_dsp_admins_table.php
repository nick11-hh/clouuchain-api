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
            $table->integer('group_id')->default(0)->comment('员工组id');
            $table->timestamp('last_login_at')->nullable()->comment('上次登录');
            $table->tinyInteger('super_admin')->default(0)->comment('是否为超管：0-否 1-是');
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
            $table->dropColumn(['group_id', 'last_login_at', 'super_admin']);
        });
    }
};
