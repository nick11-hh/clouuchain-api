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
        Schema::table('dsp_shop_order_shipping_address', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->comment('手动编辑时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order_shipping_address', function (Blueprint $table) {
            $table->dropColumn('edited_at');
        });
    }
};
