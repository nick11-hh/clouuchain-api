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
        Schema::create('dsp_crawler_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_company_id')->comment('预报快递公司ID');
            $table->string('username', 191)->nullable()->default('')->comment('账号');
            $table->string('password', 191)->nullable()->default('')->comment('密码');
            $table->bigInteger('crawler_id')->nullable()->comment('第三方爬虫ID');
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
        Schema::dropIfExists('dsp_crawler_configs');
    }
};
