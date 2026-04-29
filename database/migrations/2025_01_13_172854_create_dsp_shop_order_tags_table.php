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
        Schema::create('dsp_shop_order_tags', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable()->comment('标签名称');
            $table->integer('sort')->default(0)->comment('排序');
            $table->string('color')->default('')->comment('颜色');
            $table->string('font_color')->default('')->comment('字体颜色');
            $table->json('description')->nullable()->comment('描述');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('订单标签表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_tags');
    }
};
