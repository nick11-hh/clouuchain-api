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
        Schema::create('dsp_admin_login_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->index();
            $table->string('ip');
            $table->string('ip_location')->default('');
            $table->json('headers');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('管理端用户登录日志');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_admin_login_logs');
    }
};
