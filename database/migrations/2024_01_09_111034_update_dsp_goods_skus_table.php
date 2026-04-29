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
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->decimal('quote_price', 10)->after('purchase_price')->nullable()->comment('');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->dropColumn('quote_price');
        });
    }
};
