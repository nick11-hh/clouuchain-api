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
        Schema::create('dsp_consult', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('type')->default(0)->comment('咨询类型：0-订单咨询 1-包裹咨询');
            $table->integer('order_id')->default(0)->comment('订单id');
            $table->string('order_sn', 191)->default('')->comment('关联单号');
            $table->string('content', 191)->default('')->comment('咨询内容');
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
        Schema::dropIfExists('dsp_consult');
    }
};
