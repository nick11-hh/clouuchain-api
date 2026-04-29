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
        Schema::create('dsp_purchase_account', function (Blueprint $table) {
            $table->id();
            $table->string('account_name')->default('')->comment('账号名称');
            $table->string('platform')->default('')->comment('平台');
            $table->string('name')->default('')->comment('用户名');
            $table->tinyInteger('state')->default(0)->comment('授权状态：0-未授权 1-已授权');
            $table->tinyInteger('enable')->default(0)->comment('启用状态：0-未启用 1-已启用');
            $table->string('remark')->default('')->comment('备注');
            $table->timestamp('auth_time')->nullable()->comment('授权时间');
            $table->string('token')->default('')->comment('token');
            $table->comment('采购账号管理');
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
        Schema::dropIfExists('dsp_purchase_account');
    }
};
