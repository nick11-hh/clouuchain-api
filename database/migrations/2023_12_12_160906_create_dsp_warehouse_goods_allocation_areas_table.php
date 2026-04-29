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
        Schema::create('dsp_warehouse_goods_allocation_areas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('warehouse_id');
            $table->unsignedTinyInteger('type')->default(0)->comment('类型');
            $table->string('number')->comment('编号');
            $table->unsignedSmallInteger('column')->comment('列');
            $table->unsignedSmallInteger('row')->comment('层');
            $table->unsignedInteger('counts')->comment('货位数量');
            $table->unsignedTinyInteger('reusable')->default(0)->comment('货位重用');
            $table->integer('index')->default(0)->comment('排序值');
            $table->integer('is_locked')->default(0)->comment('状态：0为未锁定；1为已锁定');
            $table->tinyInteger('no_package_special')->nullable()->default(0)->comment('无人包裹专用1-是0-否');
            $table->boolean('for_big')->default(false)->comment('是否大货区');
            $table->tinyInteger('use_type')->default(1)->comment('货位使用类型');
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
        Schema::dropIfExists('dsp_warehouse_goods_allocation_areas');
    }
};
