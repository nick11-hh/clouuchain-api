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
        Schema::create('dsp_shop_setting', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shop_id')->comment('店铺id');
            $table->tinyInteger('send_customer_email')->default(1)->comment('是否向客户发送发货邮件');
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
        Schema::dropIfExists('dsp_shop_setting');
    }
};
