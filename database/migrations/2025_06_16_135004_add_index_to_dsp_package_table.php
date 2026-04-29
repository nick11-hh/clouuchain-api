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
        Schema::table('dsp_package_items', function (Blueprint $table) {
            $table->index('package_id');
            $table->index('shop_order_id');
            $table->index('shop_order_item_id');
        });

        Schema::table('dsp_package', function (Blueprint $table) {
            $table->index('package_sn');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_package_items', function (Blueprint $table) {
            $table->dropIndex('package_id');
            $table->dropIndex('shop_order_id');
            $table->dropIndex('shop_order_item_id');
        });

        Schema::table('dsp_package', function (Blueprint $table) {
            $table->dropIndex('package_sn');
        });
    }
};
