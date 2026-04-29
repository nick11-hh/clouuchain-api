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
        Schema::create('dsp_sku_quotation_group', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('country_id')->default(0)->comment('国家id');
            $table->bigInteger('custom_id')->default(0)->comment('客户id');
            $table->bigInteger('sku_id')->default(0)->comment('goods_sku表id');
            $table->bigInteger('goods_id')->default(0)->comment('goods表id');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sku_id','custom_id','country_id'],'idx_sku_custom_country_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_sku_quotation_group');
    }
};
