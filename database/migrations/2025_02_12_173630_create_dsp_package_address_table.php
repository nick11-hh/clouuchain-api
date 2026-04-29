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
        Schema::create('dsp_package_address', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('package_id')->comment('包裹id');
            $table->string('name')->nullable()->comment('收件人全称');
            $table->string('company')->nullable()->comment('收件人公司');
            $table->string('first_name')->nullable()->comment('收件人名');
            $table->string('last_name')->nullable()->comment('收件人姓');
            $table->string('address1')->nullable()->comment('收件人地址1');
            $table->string('address2')->nullable()->comment('收件人地址2');
            $table->string('phone')->nullable()->comment('收件人电话');
            $table->string('email')->default('')->comment('收件人邮箱');
            $table->string('city')->nullable()->comment('收件人城市');
            $table->string('zip')->nullable()->comment('收件人邮编');
            $table->string('province')->nullable()->comment('收件人省/州');
            $table->string('country')->nullable()->comment('收件人国家');
            $table->string('country_code')->nullable()->comment('收件人国家代码');
            $table->string('province_code')->nullable()->comment('省份代码');
            $table->string('tax')->nullable()->comment('收件人税号');
            $table->string('unique_key')->nullable()->comment('唯一key值');
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
        Schema::dropIfExists('dsp_package_address');
    }
};
