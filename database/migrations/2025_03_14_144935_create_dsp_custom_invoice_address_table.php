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
        Schema::create('dsp_custom_invoice_address', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->default(0)->index()->comment('客户ID');
            $table->string('name')->default('')->comment('名称');
            $table->string('first_name')->default('')->comment('名');
            $table->string('last_name')->default('')->comment('姓');
            $table->string('country')->default('')->comment('国家');
            $table->string('province')->default('')->comment('省/州');
            $table->string('city')->default('')->comment('城市');
            $table->string('address_detail')->default('')->comment('地址详情');
            $table->string('phone_area_code', 16)->default('')->comment('手机区号');
            $table->string('phone_number')->default('')->comment('手机号码');
            $table->string('email')->default('')->comment('email');
            $table->string('postcode')->default('')->comment('邮编');
            $table->string('tax')->default('')->comment('税号');
            $table->integer('world_country_id')->default(0)->comment('world_countries.id');
            $table->timestamps();
            $table->comment('客户发票地址表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_custom_invoice_address');
    }
};
