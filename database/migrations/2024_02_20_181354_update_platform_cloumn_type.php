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
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->string('platform')->change();
        });
        Schema::table('dsp_outbound_orders', function (Blueprint $table) {
            $table->string('sale_platform')->change();
        });
        \App\Models\PurchaseOrdersModel::query()->where('platform', '1')->update(['platform' => 'shopify']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_orders', function (Blueprint $table) {
            $table->integer('platform')->change();
        });
        Schema::table('dsp_outbound_orders', function (Blueprint $table) {
            $table->integer('sale_platform')->change();
        });
    }
};
