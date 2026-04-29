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
        Schema::table('dsp_client_goods_skus', function (Blueprint $table) {
            $table->index('goods_id');
            $table->index('sku_id');
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
            $table->dropIndex('goods_id');
            $table->dropIndex('sku_id');
        });
    }
};
