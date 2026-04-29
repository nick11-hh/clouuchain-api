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
        Schema::create('dsp_coupon_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('coupon_id')->comment('优惠券ID');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态');
            $table->string('code', 191)->comment('兑换码');
            $table->json('remark')->nullable()->comment('备注');
            $table->integer('total_count')->comment('总发放数');
            $table->integer('received_count')->default(0)->comment('领取数量');
            $table->integer('used_count')->default(0)->comment('使用数量');
            $table->integer('each_count')->default(1)->comment('每人领取数量');

            $table->index('code');
            $table->index('coupon_id');
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
        Schema::dropIfExists('dsp_coupon_codes');
    }
};
