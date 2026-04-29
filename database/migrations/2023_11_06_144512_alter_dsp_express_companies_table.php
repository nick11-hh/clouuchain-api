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
        Schema::table('dsp_express_companies', function (Blueprint $table) {
            $table->integer('type')->default(0)->comment('物流公司分类：具体值详见OrderDockingRecordModel中定义');
            $table->json('info')->nullable()->comment('授权配置项');
            $table->string('code')->default('')->comment('物流公司编号');
            $table->dropColumn(['freight_forwarder', 'customer_code', 'api_secret']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_companies', function (Blueprint $table) {
            $table->dropColumn(['type', 'info']);
            $table->string('freight_forwarder')->default('')->comment('贷代名称');
            $table->string('customer_code')->default('')->comment('客户编号');
            $table->string('api_secret')->default('')->comment('ApiSecret');
        });
    }
};
