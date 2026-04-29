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
        Schema::table('dsp_custom_address', function (Blueprint $table) {
            $table->string('address_type', 50)->default('shipping')->comment('地址类型');
            $table->string('phone_area_code', 16)->nullable()->comment('电话区号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_custom_address', function (Blueprint $table) {
            $table->dropColumn('address_type');
            $table->dropColumn('phone_area_code');
        });
    }
};
