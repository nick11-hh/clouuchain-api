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
        Schema::table('dsp_shop_setting', function (Blueprint $table) {
            $table->tinyInteger('auto_shop_delivery')->default(1)->comment('是否自动交运运单号到店铺');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_setting', function (Blueprint $table) {
            $table->dropColumn('auto_shop_delivery');
        });
    }
};
