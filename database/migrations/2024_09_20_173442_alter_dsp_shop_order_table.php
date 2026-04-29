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
            $table->integer('charge_type_id')->default(0)->comment('费用类型id');
            $table->decimal('supplement_price')->default(0)->comment('补收金额');
            $table->string('supplement_charge_remark')->default('')->comment('补收费用备注');
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
                                   'charge_type_id',
                                   'supplement_price',
                                   'supplement_charge_remark'
                               ]);
        });
    }
};
