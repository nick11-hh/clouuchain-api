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
        Schema::create('dsp_platform_sku_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('custom_id')->comment('客户id');
            $table->string('product_id')->comment('平台产品id');
            $table->string('platform_sku_id')->comment('平台产品sku_id');
            $table->string('custom_sku_id')->comment('平台自定义sku_id');
            $table->string('purchase_platform')->nullable()->comment('采购平台');
            $table->text('purchase_url')->nullable()->comment('采购链接');
            $table->string('purchase_product_id')->nullable()->comment('采购产品id');
            $table->string('purchase_spec_id')->nullable()->comment('采购产品spec_id');
            $table->tinyInteger('status')->default(1)->comment('状态');
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
        Schema::dropIfExists('dsp_platform_sku_mappings');
    }
};
