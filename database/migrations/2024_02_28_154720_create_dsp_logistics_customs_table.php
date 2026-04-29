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
        Schema::create('dsp_logistics_customs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('')->comment('自定义报关名称');
            $table->string('cn_name')->default('')->comment('报关中文名');
            $table->string('en_name')->default('')->comment('报关英文名');
            $table->decimal('unit_price', 10)->default(0)->comment('报关单价');
            $table->string('code')->default('')->comment('海关编码');
            $table->bigInteger('weight')->default(0)->comment('报关重量（g）');
            $table->string('material')->default('')->comment('材质');
            $table->string('use_to')->default('')->comment('用途');
            $table->string('attributes')->default('')->comment('物品属性');
            $table->softDeletes();
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
        Schema::dropIfExists('dsp_logistics_customs');
    }
};
