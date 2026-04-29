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
        Schema::create('dsp_shop', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_id')->default(0)->comment('客户id');
            $table->string('shop_name')->default('')->comment('店铺名称');
            $table->string('platform')->default('')->comment('平台');
            $table->tinyInteger('status')->default(0)->comment('授权状态：0-未授权 1-授权成功');
            $table->tinyInteger('enable')->default(1)->comment('启用状态：0-未启用 1-已启用');
            $table->timestamp('authorize_at')->nullable()->comment('授权时间');
            $table->string('shop_url')->default(0)->comment('店铺链接');
            $table->string('access_token')->default('');
            $table->timestamp('deleted_at')->nullable();
            $table->index(['customer_id', 'shop_name']);
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
        Schema::dropIfExists('dsp_shop');
    }
};
