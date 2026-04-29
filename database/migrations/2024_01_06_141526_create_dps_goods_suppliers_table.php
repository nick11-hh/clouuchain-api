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
        Schema::create('dsp_goods_suppliers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('goods_sku_id')->comment('本地产品sku id');
            $table->bigInteger('supplier_id')->comment('供应商id');
            $table->decimal('price', 10)->comment('供应商报价');
            $table->string('currency')->comment('报价币种');
            $table->tinyInteger('purchase_type')->comment('采购渠道  1 1688 2 线下');
            $table->tinyText('purchase_url')->nullable()->comment('采购链接');
            $table->string('purchase_goods_name')->nullable()->comment('采购商品名称');
            $table->string('purchase_spec_image')->nullable()->comment('采购商品规格图片');
            $table->string('purchase_spec_name')->nullable()->comment('采购商品规格');
            $table->string('purchase_goods_id')->nullable()->comment('采购商品id');
            $table->string('purchase_sku_id')->nullable()->comment('采购的sku id');
            $table->string('purchase_spec_id')->nullable()->comment('采购的spec id');
            $table->tinyInteger('status')->default(1)->comment('供货关系状态');
            $table->tinyInteger('is_default')->default(0)->comment('默认供应商（多供应商时适用）');
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
        Schema::dropIfExists('dsp_goods_suppliers');
    }
};
