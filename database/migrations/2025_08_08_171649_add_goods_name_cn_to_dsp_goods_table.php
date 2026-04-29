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
            $table->string('goods_name_cn')->nullable()->comment('产品中文名称');
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
            $table->dropColumn('goods_name_cn');
        });
    }
};
