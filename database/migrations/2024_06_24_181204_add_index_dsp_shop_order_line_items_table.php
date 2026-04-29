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
        Schema::table('dsp_order_item_stocks', function (Blueprint $table) {
            $table->index('order_id');
            $table->index('order_item_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_order_item_stocks', function (Blueprint $table) {
            $table->dropIndex('order_id');
            $table->dropIndex('order_item_id');
        });
    }
};
