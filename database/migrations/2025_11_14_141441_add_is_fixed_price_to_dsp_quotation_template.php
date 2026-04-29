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
        Schema::table('dsp_quotation_template', function (Blueprint $table) {
            // 新增 is_fixed_price 字段
            $table->tinyInteger('is_fixed_price')->default(0)->comment('0=非一口价 1=一口价');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_quotation_template', function (Blueprint $table) {
            // 回滚时删除字段
            $table->dropColumn('is_fixed_price');
        });
    }
};
