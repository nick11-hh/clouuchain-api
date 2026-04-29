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
        Schema::create('dsp_outbound_order_addresses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('outbound_id')->comment('出库单id');
            $table->string('name')->comment('用户名称');
            $table->string('first_name')->nullable()->comment('用户名称');
            $table->string('last_name')->nullable()->comment('用户名称');
            $table->string('address')->comment('详细地址');
            $table->string('address2')->nullable()->comment('补充地址');
            $table->string('phone')->comment('联系电话');
            $table->string('city')->comment('城市');
            $table->string('zip')->nullable()->comment('邮编');
            $table->string('province')->comment('省份');
            $table->string('country')->comment('国家');
            $table->string('company')->nullable()->comment('公司');
            $table->string('latitude')->nullable()->comment('经度');
            $table->string('longitude')->nullable()->comment('纬度');
            $table->string('tax')->nullable()->comment('税号');
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
        Schema::dropIfExists('dsp_outbound_order_addresses');
    }
};
