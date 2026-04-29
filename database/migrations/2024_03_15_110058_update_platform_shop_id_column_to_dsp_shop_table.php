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
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->string('platform_shop_id')->after('shop_auth_id')->nullable()->comment('店铺平台id')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->string('platform_shop_id')->after('shop_auth_id')->comment('店铺平台id')->change();
        });
    }
};
