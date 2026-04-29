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
            $table->tinyInteger('quote_status')->default(1)->comment('报价状态 1 未报价 2 报价中 3 已报价');
            $table->text('quote_remark')->nullable()->comment('报价备注');
            $table->bigInteger('logistics_channel_id')->nullable()->comment('物流渠道');
            $table->bigInteger('country_id')->nullable()->comment('物流国家id');
            $table->integer('reference_time')->nullable()->comment('时效');
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
            $table->dropColumn('quote_status');
            $table->dropColumn('quote_remark');
            $table->dropColumn('logistics_channel_id');
            $table->dropColumn('country_id');
            $table->dropColumn('reference_time');
        });
    }
};
