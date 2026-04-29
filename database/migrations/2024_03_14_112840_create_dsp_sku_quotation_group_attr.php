<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_sku_quotation_group_attr', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('parent_id')->default(0)->comment('sku_quotation_group表id');
            $table->integer('quantity')->default(0)->comment('数量');
            $table->decimal('price')->default(0.00)->comment('金额');
            $table->tinyInteger('is_new')->default(1)->comment('是否最新记录:1 是,0 否');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id'], 'idx_parent_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_sku_quotation_group_attr');
    }
};
