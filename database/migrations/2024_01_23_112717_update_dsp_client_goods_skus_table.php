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
        Schema::table('dsp_client_goods_skus', function (Blueprint $table) {
            $table->decimal('cost_price', 10)->nullable()->comment('成本价');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_client_goods_skus', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
