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
        Schema::create('dsp_express_order_address', function (Blueprint $table) {
            $table->id();
            $table->integer('express_order_id')->default(0)->comment('物流订单ID')->index();
            $table->string('consignee_name')->nullable()->comment('收件人全称');
            $table->string('consignee_company')->nullable()->comment('收件人公司');
            $table->string('consignee_first_name')->nullable()->comment('收件人名');
            $table->string('consignee_last_name')->nullable()->comment('收件人姓');
            $table->string('consignee_address1')->nullable()->comment('收件人地址1');
            $table->string('consignee_address2')->nullable()->comment('收件人地址2');
            $table->string('consignee_phone')->nullable()->comment('收件人电话');
            $table->string('consignee_email')->default('')->comment('收件人邮箱');
            $table->string('consignee_city')->nullable()->comment('收件人城市');
            $table->string('consignee_zip')->nullable()->comment('收件人邮编');
            $table->string('consignee_province')->nullable()->comment('收件人省/州');
            $table->string('consignee_country')->nullable()->comment('收件人国家');
            $table->string('consignee_country_code')->nullable()->comment('收件人国家代码');
            $table->string('consignee_tax')->nullable()->comment('收件人税号');

            $table->timestamps();
            $table->softDeletes();
            $table->comment('物流订单地址表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_order_address');
    }
};
