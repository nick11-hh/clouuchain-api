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
        Schema::create('dsp_member_level', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->comment('会员名称');
            $table->bigInteger('growth_value')->nullable()->default(0)->index('dsp_member_level_growth_value_index')->comment('成长值');
            $table->unsignedTinyInteger('enabled')->nullable()->default(1)->comment('状态1-开启0-禁用');
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
        Schema::dropIfExists('dsp_member_level');
    }
};
