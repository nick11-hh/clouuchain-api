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
        Schema::create('dsp_custom_address', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('custom_id')->default(0)->comment('客户ID');
            $table->string('first_name')->default('')->comment('姓');
            $table->string('last_name')->default('')->comment('名');
            $table->bigInteger('country_id')->default(0)->comment('所属国家id');
            $table->string('province')->default('')->comment('省/州');
            $table->string('city')->default('')->comment('城市');
            $table->string('address_detail')->default('')->comment('地址详情');
            $table->string('phone_number')->default('')->comment('手机号码');
            $table->string('email')->default('')->comment('email');
            $table->string('post_code')->default('')->comment('邮编');
            $table->string('tax_id')->default('')->comment('税号');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认地址');
            $table->timestamps();
            $table->softDeletes();

            $table->index('custom_id');

            $table->comment('客户地址表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_custom_address');
    }
};
