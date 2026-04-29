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
        Schema::table('dsp_order_resources', function (Blueprint $table) {
            $table->json('country_ids')->nullable()->comment('报价国家(存在多个)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_order_resources', function (Blueprint $table) {
            $table->dropColumn(['country_ids']);
        });
    }
};
