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
        Schema::create('dsp_express_line_to_member_levels_table', function (Blueprint $table) {
            $table->bigInteger('member_level_id')->comment('会员等级ID');
            $table->bigInteger('express_line_id')->comment('渠道ID');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_line_to_member_levels_table');
    }
};
