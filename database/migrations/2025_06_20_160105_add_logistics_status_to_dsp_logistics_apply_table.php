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
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->string('logistics_carrier', 50)->nullable()->comment('物流服务商')->index();
            $table->string('tracking_status', 50)->default('NotFound')->comment('物流追踪状态')->index();
            $table->string('tracking_platform', 50)->nullable()->comment('追踪平台');
            $table->string('logistics_type')->default(1)->comment('物流类型 1 头程 2 尾程');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->dropColumn('logistics_carrier');
            $table->dropColumn('tracking_status');
            $table->dropColumn('tracking_platform');
            $table->dropColumn('logistics_type');
        });
    }
};
