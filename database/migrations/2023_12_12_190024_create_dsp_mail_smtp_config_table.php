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
        Schema::create('dsp_mail_smtp_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('host')->nullable()->comment('域名');
            $table->string('port')->default('25')->comment('端口');
            $table->string('encryption')->nullable()->comment('加密方式');
            $table->string('username')->nullable()->comment('用户名');
            $table->string('password')->nullable()->comment('密码');
            $table->string('from_address')->nullable()->comment('发件人邮箱');
            $table->string('from_name')->nullable()->comment('发件人姓名');
            $table->bigInteger('company_id')->index('jiyun_mail_smtp_config_company_id_index');
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
        Schema::dropIfExists('dsp_mail_smtp_config');
    }
};
