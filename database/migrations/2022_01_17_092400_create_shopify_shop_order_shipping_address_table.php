<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopifyShopOrderShippingAddressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_shop_order_shipping_address', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->default(0);
            $table->string('first_name')->nullable()->comment('名');
            $table->string('last_name')->nullable()->comment('姓');
            $table->string('address1')->nullable()->comment('街道');
            $table->string('address2')->nullable()->comment('公寓门牌号');
            $table->string('phone')->nullable()->comment('电话');
            $table->string('city')->nullable()->comment('城市');
            $table->string('zip')->nullable()->comment('邮编');
            $table->string('province')->nullable()->comment('GRAPHQL');
            $table->string('country')->nullable()->comment('国家');
            $table->string('company')->nullable()->comment('公司');
            $table->string('latitude')->nullable()->comment('维度');
            $table->string('longitude')->nullable()->comment('经度');
            $table->string('name')->nullable()->comment('全名');
            $table->string('country_code')->nullable()->comment('国家代码');
            $table->string('province_code')->nullable()->comment('省代码');
            $table->timestamp('deleted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_shipping_address');
    }
}
