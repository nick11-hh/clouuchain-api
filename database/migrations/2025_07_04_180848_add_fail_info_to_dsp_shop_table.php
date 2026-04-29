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
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->text('fail_info')->nullable()->comment('店铺接口调用失败信息');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->dropColumn('fail_info');
        });
    }
};
