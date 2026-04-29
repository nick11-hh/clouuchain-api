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
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->string('shop_auth_id')->after('access_token')->nullable()->comment('shou auth 表 id');
            $table->string('platform_shop_id')->after('shop_auth_id')->comment('店铺平台id');
            $table->string('platform_shop_code')->after('platform_shop_id')->nullable()->comment('店铺平台编码');
            $table->string('region')->after('platform_shop_code')->nullable()->comment('销售国家地区');
            $table->tinyInteger('seller_type')->after('region')->default(1)->comment('销售类型 1 本土店铺 2 跨境店铺');
            $table->json('ext_data')->after('seller_type')->nullable()->comment('店铺其他数据');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->dropColumn('platform_shop_id');
            $table->dropColumn('platform_shop_code');
            $table->dropColumn('region');
            $table->dropColumn('seller_type');
            $table->dropColumn('ext_data');
        });
    }
};
