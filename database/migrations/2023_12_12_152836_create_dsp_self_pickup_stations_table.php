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
        Schema::create('dsp_self_pickup_stations', function (Blueprint $table) {
            $table->comment('自提点');
            $table->bigIncrements('id');
            $table->json('name');
            $table->bigInteger('country_id')->index('dsp_self_pickup_stations_country_id_index');
            $table->string('address', 191)->comment('地址');
            $table->string('contactor', 191)->nullable()->comment('联系人');
            $table->string('contact_info', 191)->nullable()->comment('联系电话');
            $table->json('opening_hours');
            $table->json('announcement');
            $table->bigInteger('rule_id')->nullable();
            $table->bigInteger('area_id')->nullable();
            $table->bigInteger('sub_area_id')->nullable();
            $table->decimal('lon', 18, 10)->nullable();
            $table->decimal('lat', 18, 10)->nullable();
            $table->json('store_time')->nullable();
            $table->json('overdue_fee')->nullable();
            $table->json('remark')->nullable();
            $table->integer('edit_notice_jurisdiction')->default(1)->comment('编辑公告权限：0为不能；1为能');
            $table->integer('is_delivery')->default(1)->comment('0为不能；1为能');
            $table->boolean('enabled')->default(true)->index('dsp_self_pickup_stations_enabled_index')->comment('是否开启');
            $table->string('code', 191)->default('')->comment('自提点编号');
            $table->bigInteger('limit_one_weight')->nullable()->default(1000000)->comment('限制单箱重量');
            $table->bigInteger('limit_many_weight')->nullable()->default(1000000)->comment('限制整票重量');
            $table->bigInteger('limit_length')->nullable()->default(1000000)->comment('限制单箱长度');
            $table->boolean('allow_all_order')->default(true)->comment('允许所有订单入库，包括用户选择的非该自提点');
            $table->boolean('notify_after_received')->default(true)->comment('任意自提点入库发送通知');
            $table->bigInteger('index')->default(0)->comment('排序值');
            $table->tinyInteger('is_stg')->nullable()->default(0)->comment('同行货专用0-否1-是');
            $table->bigInteger('payment_type')->nullable()->comment('支付类型id （支付配置中获取）');
            $table->string('settlement_bank', 191)->nullable()->comment('结算银行名称');
            $table->string('settlement_account_name', 191)->nullable()->comment('结算账户名称');
            $table->string('settlement_account', 191)->nullable()->comment('结算账号');
            $table->string('settlement_remark', 191)->nullable()->comment('结算备注');
            $table->string('image', 191)->nullable()->comment('自定义自提点图片');
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
        Schema::dropIfExists('dsp_self_pickup_stations');
    }
};
