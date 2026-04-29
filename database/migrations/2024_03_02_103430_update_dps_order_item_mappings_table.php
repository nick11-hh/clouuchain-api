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
        Schema::table('dps_order_item_mappings', function (Blueprint $table) {
            $table->string('platform')->change();
        });
        \App\Models\OrderItemMapping::query()->where('platform', '1')->update(['platform' => 'shopify']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dps_order_item_mappings', function (Blueprint $table) {
            $table->tinyInteger('platform')->change();
        });
    }
};
