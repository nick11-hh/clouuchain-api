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
        Schema::create('dsp_consult_content', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('consult_id')->default(0);
            $table->integer('user_id')->default(0)->comment('用户id');
            $table->integer('customer_service_id')->default(0)->comment('客服id');
            $table->string('content', 191)->default('')->comment('咨询内容');
            $table->tinyInteger('status')->default(0)->comment('状态：0-未读 1-已读');
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
        Schema::dropIfExists('dsp_consult_content');
    }
};
