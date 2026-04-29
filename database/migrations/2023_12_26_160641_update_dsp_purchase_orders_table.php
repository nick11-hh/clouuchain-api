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
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->integer('platform')->after('shop_id')->comment('采购平台');
            $table->bigInteger('provider_id')->default(0)->comment('供应商id');
            $table->bigInteger('purchase_user_id')->comment('采购员id');
            $table->text('remark')->nullable()->comment('备注');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('platform');
            $table->dropColumn('provider_id');
            $table->dropColumn('purchase_user_id');
            $table->dropColumn('remark');
        });
    }
};
