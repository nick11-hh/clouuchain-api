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
        Schema::create('dsp_logistics_tracking', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('logistics_id')->comment('物流id dsp_logistics_apply 表 id');
            $table->string('tracking_status', 50)->comment('轨迹状态');
            $table->string('tracking_sub_status', 50)->nullable()->comment('物流子状态');
            $table->text('description')->nullable()->comment('轨迹描述');
            $table->timestamp('event_time')->comment('轨迹时间');
            $table->string('event_zone', 20)->comment('时间时区');
            $table->string('location')->nullable()->comment('轨迹地点');
            $table->timestamps();
            $table->softDeletes();
            $table->index('logistics_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_logistics_tracking');
    }
};
