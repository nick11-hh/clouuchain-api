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
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->string('spec_name_cn')->nullable()->comment('中文规格名称')->after('spec_name');
        });
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->string('spec_name_cn')->nullable()->comment('中文规格名称')->after('spec_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->dropColumn('spec_name_cn');
        });
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->dropColumn('spec_name_cn');
        });
    }
};
