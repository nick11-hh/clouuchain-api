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
        Schema::create('dsp_sale_prices_user_levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_price_id')->index('dsp_sale_prices_user_levels_sale_price_id_index');
            $table->bigInteger('user_level_id')->index('dsp_sale_prices_user_levels_user_level_id_index');
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
        Schema::dropIfExists('dsp_sale_prices_user_levels');
    }
};
