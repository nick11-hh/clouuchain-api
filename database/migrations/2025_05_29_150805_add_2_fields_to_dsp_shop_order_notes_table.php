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
        Schema::table('dsp_shop_order_notes', function (Blueprint $table) {
            $table->text('notes')->nullable()->comment('订单备注');
            $table->string('added_by_user')->nullable();
            $table->string('customer_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_notes', function (Blueprint $table) {
            $table->dropColumn(['notes', 'added_by_user', 'customer_note']);
        });
    }
};
