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
            $table->json('apply_country_ids')->nullable()->comment('提交报价国家ids');
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
            $table->dropColumn('apply_country_ids');
        });
    }
};
