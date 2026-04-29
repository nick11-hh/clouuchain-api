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
            $table->decimal('profit', 10)->default(0)->comment('利润(CNY)')->after('purchase_price');
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
            $table->dropColumn('profit');
        });
    }
};
