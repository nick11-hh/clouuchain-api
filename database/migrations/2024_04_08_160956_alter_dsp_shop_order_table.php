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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->decimal('vendor_change_price', 10, 2)->default(0)->comment('供应商改价')->after('vendor_price');

            $table->text('change_price_remark')->nullable()->comment('改价备注');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->dropColumn([
                'vendor_change_price',
                'change_price_remark',
            ]);
        });
    }
};
