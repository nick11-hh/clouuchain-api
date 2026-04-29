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
        Schema::create('dsp_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('姓名');
            $table->string('username')->default('')->comment('用户名');
            $table->string('phone')->default('')->comment('手机号');
            $table->string('email')->default('')->unique()->comment('邮箱');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->default('')->comment('密码');
            $table->timestamp('deleted_at')->nullable()->comment('删除时间');
            $table->tinyInteger('enable')->default(1)->comment('启用状态：0-禁用 1-启用');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_admins');
    }
};
