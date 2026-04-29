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
        Schema::create('dsp_unpacking_and_inventory_configuration', function (Blueprint $table) {
            $table->comment('拆包清点配置表');
            $table->bigIncrements('id');
            $table->bigInteger('company_id')->comment('公司id');
            $table->json('inventory_items')->nullable()->comment('清点项目');
            $table->integer('prop_id')->nullable()->comment('货物属性 id');
            $table->decimal('length')->nullable()->comment('货品长度');
            $table->decimal('width')->nullable()->comment('货品宽度');
            $table->decimal('height')->nullable()->comment('货品高度');
            $table->decimal('weight', 10, 3)->nullable()->comment('货品重量 单位：KG');
            $table->string('name', 191)->default('')->comment('货品名称');
            $table->bigInteger('qty')->default(0)->comment('货品数量');
            $table->decimal('price')->nullable()->comment('货品价值');
            $table->integer('goods_status')->nullable()->comment('货物状态 O-正常件 1-异常件');
            $table->integer('status')->default(0)->comment('配置状态 O-禁用 1-启用');
            $table->json('category')->nullable()->comment('货品分类');
            $table->json('require_fields')->nullable()->comment('必填字段');
            $table->integer('spu_enabled')->nullable()->comment('集运模式0-标准1-电商');
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
        Schema::dropIfExists('dsp_unpacking_and_inventory_configuration');
    }
};
