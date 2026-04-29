<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_goods_skus', function (Blueprint $table) {
            $table->index('sku_id');
            $table->index('goods_id');
            $table->index('system_sku');
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
            $table->dropIndex('sku_id');
            $table->dropIndex('goods_id');
            $table->dropIndex('system_sku');
        });
    }
};
