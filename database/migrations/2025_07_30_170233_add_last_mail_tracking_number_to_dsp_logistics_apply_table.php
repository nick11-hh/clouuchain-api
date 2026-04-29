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
            $table->dropColumn('logistics_type');
            $table->string('last_mail_tracking_number')->nullable()->comment('尾程单号');
            $table->timestamp('last_mail_time')->nullable()->comment('尾程单号获取时间');
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
            $table->tinyInteger('logistics_type')->default(1)->comment('物流类型 1 头程 2 尾程');
            $table->dropColumn('last_mail_tracking_number');
//            $table->dropColumn('last_mail_time');
        });
    }
};
