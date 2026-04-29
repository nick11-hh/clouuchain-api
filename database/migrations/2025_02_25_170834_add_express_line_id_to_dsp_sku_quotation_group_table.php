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
        Schema::table('dsp_sku_quotation_group', function (Blueprint $table) {
            $table->bigInteger('express_line_id')->nullable()->comment('物流渠道');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_sku_quotation_group', function (Blueprint $table) {
            $table->dropColumn('express_line_id');
        });
    }
};
