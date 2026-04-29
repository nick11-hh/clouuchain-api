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
        Schema::create('dsp_warehouse_goods_allocations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->bigInteger('area_id')->comment('仓库货区id');
            $table->integer('column')->comment('列');
            $table->integer('row')->comment('行');
            $table->string('code')->comment('货位编码');
            $table->integer('max_count')->default(1)->comment('总可用数量');
            $table->integer('used_count')->default(0)->comment('已使用数量');
            $table->tinyInteger('is_locked')->default(0)->comment('是否被锁定');
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
        Schema::dropIfExists('dsp_warehouse_goods_allocations');
    }
};
