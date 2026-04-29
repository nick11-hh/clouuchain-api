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
        Schema::table('dsp_goods_categories', function (Blueprint $table) {
            $table->json('name_translate')->nullable()->comment('分类名称-多语言');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_categories', function (Blueprint $table) {
            $table->dropColumn(['name_translate']);
        });
    }
};
