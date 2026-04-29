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
        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            $table->mediumInteger('minimum_chargeable_weight')->default(0)->comment('最低计费重量(g)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_express_line_regions', function (Blueprint $table) {
            $table->dropColumn(['minimum_chargeable_weight']);
        });
    }
};
