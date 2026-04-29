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
        Schema::create('credit_card_recharge_record', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement();
            $table->unsignedInteger('user_id')->comment('用户id');
            $table->unsignedInteger('custom_id')->comment('客户id');
            $table->unsignedTinyInteger('type')->comment('类型:1=40Seas');
            $table->string('transaction_id')->comment('交易编号');
            $table->decimal('amount', 8, 2)->unsigned()->comment('金额');
            $table->string('currency')->comment('货币类型');
            $table->unsignedTinyInteger('status')->comment('状态:1=待支付,2=支付成功');
            $table->unsignedInteger('check_admin_id')->nullable()->comment('核账管理员id');
            $table->json('check_images')->nullable()->comment('核账图片');
            $table->string('check_desc', 255)->nullable()->comment('核账描述');
            $table->unsignedTinyInteger('check_status')->default(0)->comment('核账状态：0=未核账，1=已核账');
            $table->dateTime('check_time')->nullable()->comment('核验时间');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('updated_at')->comment('更新时间');
            $table->comment('信用卡充值记录');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_card_recharge_record');
    }
};
