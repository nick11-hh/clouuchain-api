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
        Schema::table('dsp_goods_suppliers', function (Blueprint $table) {
            $table->index('goods_sku_id');
            $table->index('supplier_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_goods_suppliers', function (Blueprint $table) {
            $table->dropIndex('goods_sku_id');
            $table->dropIndex('supplier_id');
        });
    }
};
