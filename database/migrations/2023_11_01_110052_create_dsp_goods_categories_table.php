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
        Schema::create('dsp_goods_categories', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id')->default(0)->comment('父级分类id');
            $table->string('name')->comment('分类名称');
            $table->string('description')->nullable()->comment('分类描述');
            $table->string('image')->nullable()->comment('分类图片');
            $table->tinyInteger('status')->comment('分类状态');
            $table->integer('sort')->default(100)->comment('父级分类id');
            $table->integer('operator')->nullable()->comment('父级分类id');
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
        Schema::dropIfExists('dsp_goods_categories');
    }
};
