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
        Schema::table('dsp_purchase_plan_items', function (Blueprint $table) {
            $table->json('images')->nullable()->comment('商品图片')->change();
            $table->integer('plan_qty')->default(0)->comment('计划采购数量')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_plan_items', function (Blueprint $table) {
            $table->string('images')->nullable()->comment('商品图片')->change();
            $table->string('plan_qty')->comment('采购数量')->change();
        });
    }
};
