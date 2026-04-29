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
        Schema::create('credit_card_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('类型');
            $table->string('client_id')->nullable()->comment('client_id');
            $table->string('client_secret')->nullable()->comment('client_secret');
            $table->string('webhook_secret')->nullable()->comment('webhook_secret');
            $table->decimal('minimum_payment', 8, 2)->unsigned()->default(1.00)->comment('最低支付金额');
            $table->decimal('service_charge_rate', 10, 2)->unsigned()->default(0.00)->comment('手续费比例 0-100');
            $table->decimal('service_charge_amount', 10, 2)->unsigned()->default(0.00)->comment('手续费金额');
            $table->unsignedTinyInteger('status')->default(2)->comment('状态:1=开启，2=关闭');
            $table->dateTime('created_at')->comment('创建时间');
            $table->dateTime('updated_at')->comment('更新时间');
            $table->comment('信用卡配置表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credit_card_types');
    }
};
