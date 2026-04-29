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
        Schema::create('company_new_cus_felfare', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('new_cus_send')->default(0)->comment('新用户是否送券');
            $table->tinyInteger('invitor_send')->default(0)->comment('邀请新人是否送券');
            $table->tinyInteger('invited_send')->default(0)->comment('被邀请人是否送券');
            $table->string('name')->comment('发送的优惠券名称');
            $table->unsignedMediumInteger('amount')->comment('优惠券面额 分');
            $table->unsignedMediumInteger('threshold')->comment('最低消费金额 分');
            $table->unsignedMediumInteger('day')->comment('优惠券可用天数');
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
        Schema::dropIfExists('company_new_cus_felfare');
    }
};
