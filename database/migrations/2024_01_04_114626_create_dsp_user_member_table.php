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
        Schema::create('dsp_user_member', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index('dsp_user_member_user_id_index')->comment('用户ID');
            $table->bigInteger('growth_value')->nullable()->default(0)->index('dsp_user_member_growth_value_index')->comment('可用成长值');
            $table->bigInteger('point')->nullable()->default(0)->index('dsp_user_member_point_index')->comment('可用积分');
            $table->bigInteger('level_id')->nullable()->comment('会员等级ID');
            $table->bigInteger('lock_point')->nullable()->default(0)->comment('锁定积分');
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
        Schema::dropIfExists('dsp_user_member');
    }
};
