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
        Schema::create('dsp_goods_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->comment('规则编码');
            $table->string('name')->comment('规则名称');
            $table->bigInteger('customer_id')->comment('客户id');
            $table->bigInteger('staff_id')->comment('设置的员工id');
            $table->string('discount_type')->comment('优惠类型: percentage,fixed_amount');
            $table->decimal('discount_value')->default(0)->comment('优惠值');
            $table->tinyInteger('status')->default(1)->comment('状态 1 启用 0 弃用');
            $table->timestamps();
            $table->softDeletes();
            $table->index('customer_id');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_goods_discount_rules');
    }
};
