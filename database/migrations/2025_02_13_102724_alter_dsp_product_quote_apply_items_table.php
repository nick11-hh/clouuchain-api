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
        Schema::table('dsp_product_quote_apply_items', function (Blueprint $table) {
            $table->dropColumn('apply_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_product_quote_apply_items', function (Blueprint $table) {
            $table->bigInteger('apply_id')->comment('申请id');
        });
    }
};
