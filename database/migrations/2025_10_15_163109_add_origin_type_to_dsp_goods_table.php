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
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->tinyInteger('origin_type')->default(0)->comment('商品来源类型：1=1688，2=自营');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->dropColumn('origin_type');
        });
    }
};
