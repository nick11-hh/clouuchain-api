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
            $table->timestamp('apply_quote_time')->nullable()->comment('申请报价时间');
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
            $table->dropColumn('apply_quote_time');
        });
    }
};
