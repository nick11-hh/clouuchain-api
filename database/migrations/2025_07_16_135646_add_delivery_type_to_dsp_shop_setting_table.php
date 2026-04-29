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
            $table->tinyInteger('delivery_type')->default(1)->comment('交运类型');
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
            $table->dropColumn('delivery_type');
        });
    }
};
