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
        Schema::create('dsp_charge_types', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(1)->comment('费用类型 1-订单');
            $table->string('name')->comment('费用名称');
            $table->string('remark')->default('')->comment('费用说明');
            $table->tinyInteger('status')->default(1)->comment('状态 1-启用 0-禁用');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('费用类型表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_charge_types');
    }
};
