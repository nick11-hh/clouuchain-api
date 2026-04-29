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
        Schema::create('dsp_sale_prices_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_price_id')->index('dsp_sale_prices_users_sale_price_id_index');
            $table->bigInteger('user_id')->index('dsp_sale_prices_users_user_id_index');
            $table->string('service_type', 191)->default('base');
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
        Schema::dropIfExists('dsp_sale_prices_users');
    }
};
