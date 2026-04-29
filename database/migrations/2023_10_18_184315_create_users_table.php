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
        Schema::create('dsp_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->comment('用户名')->index();
            $table->string('password')->comment('密码');
            $table->string('name')->nullable()->comment('昵称');
            $table->string('phone')->comment('手机号码')->index();
            $table->string('email')->comment('邮箱')->index();
            $table->string('status')->default(1)->comment('用户状态')->index();
            $table->tinyInteger('custom_id')->comment('客户主体id')->index();
            $table->integer('group_id')->nullable()->comment('用户所属分组')->index();
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_users');
    }
};
