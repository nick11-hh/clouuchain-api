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
        Schema::create('dsp_user_coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index('dsp_user_coupons_user_id_index');
            $table->bigInteger('coupon_id')->index('dsp_user_coupons_coupon_id_index');
            $table->string('coupon_code', 50)->comment('券码');
            $table->timestamp('used_at')->nullable()->comment('使用时间');
            $table->string('order_number')->default('')->index('dsp_user_coupons_order_number_index')->comment('关联订单号');
            $table->unsignedMediumInteger('order_amount')->default(0)->comment('订单金额');
            $table->bigInteger('discount_amount')->default(0)->comment('优惠券实际优惠价格');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->tinyInteger('enabled')->default(1)->comment('优惠券是否可用 1 位可用,0 为已作废');
            $table->bigInteger('code_id')->nullable()->comment('兑换券码ID');
            $table->timestamp('effected_at')->nullable()->comment('生效时间');
            $table->timestamp('expired_at')->nullable()->comment('过期时间');
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
        Schema::dropIfExists('dsp_user_coupons');
    }
};
