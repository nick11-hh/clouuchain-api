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
        Schema::create('dsp_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name')->comment('供应商名称');
            $table->string('supplier_code')->comment('供应商编码');
            $table->integer('type')->comment('供应商类型 1 1688 2 线下');
            $table->tinyText('supplier_url')->nullable()->comment('供应商链接');
            $table->tinyInteger('payment_method')->nullable()->comment('结算方式 1 现结 2 月结');
            $table->string('payment_name')->nullable()->comment('收款人');
            $table->string('payment_bank')->nullable()->comment('开户行');
            $table->string('payment_account')->nullable()->comment('收款账号');
            $table->tinyText('payment_remark')->nullable()->comment('结算备注');
            $table->string('contact_name')->nullable()->comment('联系人');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->string('contact_email')->nullable()->comment('联系电话');
            $table->string('contact_wechat')->nullable()->comment('联系电话');
            $table->tinyText('contact_address')->nullable()->comment('地址信息');
            $table->tinyText('remark')->nullable()->comment('供应商备注');
            $table->tinyInteger('status')->default(1)->comment('供应商状态  1 启用 0 禁用');
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
        Schema::dropIfExists('dsp_suppliers');
    }
};
