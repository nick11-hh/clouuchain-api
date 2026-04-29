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
        Schema::create('dsp_plan_purchase_relation', function (Blueprint $table) {
            $table->id();
            $table->integer('plan_id')->default(0)->comment('采购计划id');
            $table->integer('purchase_id')->default(0)->comment('采购表id');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('采购和采购计划关系表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_plan_purchase_relation');
    }
};
