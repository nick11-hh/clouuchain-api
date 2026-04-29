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
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->string('purchase_platform')->nullable()->comment('采集平台');
            $table->string('purchase_product_id')->nullable()->comment('采集产品id');
        });
        Schema::table('dsp_client_goods_skus', function (Blueprint $table) {
            $table->string('purchase_spec_id')->nullable()->comment('采集spec_id平台');
        });

        Schema::table('dsp_admin_collect_goods', function (Blueprint $table) {
            $table->string('purchase_product_id')->nullable()->comment('采集产品id');
        });
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->string('purchase_spec_id')->nullable()->comment('采集spec_id平台');
        });

        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->string('purchase_platform')->nullable()->comment('采集平台');
            $table->string('purchase_product_id')->nullable()->comment('采集产品id');
        });
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->string('purchase_spec_id')->nullable()->comment('采集spec_id平台');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_client_goods', function (Blueprint $table) {
            $table->dropColumn('purchase_platform');
            $table->dropColumn('purchase_product_id');
        });
        Schema::table('dsp_client_goods_skus', function (Blueprint $table) {
            $table->dropColumn('purchase_spec_id');
        });

        Schema::table('dsp_admin_collect_goods', function (Blueprint $table) {
            $table->dropColumn('purchase_product_id');
        });
        Schema::table('dsp_admin_collect_goods_skus', function (Blueprint $table) {
            $table->dropColumn('purchase_spec_id');
        });

        Schema::table('dsp_goods', function (Blueprint $table) {
            $table->dropColumn('purchase_platform');
            $table->dropColumn('purchase_product_id');
        });
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->dropColumn('purchase_spec_id');
        });
    }
};
