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
        Schema::create('dsp_ctu_message_to_user_groups', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_group_id')->comment('客户组ID');
            $table->bigInteger('message_id')->comment('消息ID');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('消息通知表-用户组关联表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_ctu_message_to_user_groups');
    }
};
