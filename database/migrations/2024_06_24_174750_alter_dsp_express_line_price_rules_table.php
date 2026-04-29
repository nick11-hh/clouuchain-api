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
        Schema::table('dsp_express_line_price_rules', function (Blueprint $table) {
            $table->bigInteger('region_id')->default(0)->comment('分区ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_line_price_rules', function (Blueprint $table) {
            $table->dropColumn([
                'region_id',
            ]);
        });
    }
};
