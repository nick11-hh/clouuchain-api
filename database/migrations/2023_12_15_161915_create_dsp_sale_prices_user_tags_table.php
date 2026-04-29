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
        Schema::create('dsp_sale_prices_user_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_tag_id');
            $table->bigInteger('sale_price_id');
            $table->string('service_type', 191)->default('base');
            $table->index(['user_tag_id', 'sale_price_id'], 'dsp_sale_prices_user_tags_user_tag_id_sale_price_id_index');
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
        Schema::dropIfExists('dsp_sale_prices_user_tags');
    }
};
