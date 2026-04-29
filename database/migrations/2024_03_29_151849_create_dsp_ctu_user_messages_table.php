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
        Schema::create('dsp_ctu_user_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->comment('客户ID');
            $table->bigInteger('message_id')->comment('消息ID');
            $table->tinyInteger('is_read')->default(0)->nullable()->comment('是否已读');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('用户消息通知表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_ctu_user_messages');
    }
};
