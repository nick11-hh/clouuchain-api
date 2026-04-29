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
        Schema::table('dsp_platform_products', function (Blueprint $table) {
            $table->smallInteger('reject_reason')->default(0)->comment('拒绝报价理由 1=暂无现货 2=价格太高 3=没有库存 4=其他')->after('quote_remark');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_platform_products', function (Blueprint $table) {
            $table->dropColumn('reject_reason');
        });
    }
};
