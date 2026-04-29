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
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->string('fulfillment_express_line')->default('')->comment('履约平台物流渠道')->after('way_bill_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_logistics_apply', function (Blueprint $table) {
            $table->dropColumn('fulfillment_express_line');
        });
    }
};
