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
        Schema::create('dsp_coupon_usable_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('coupon_id')->comment('优惠券ID');
            $table->string('express_line_id')->comment('线路ID');

            $table->index(['coupon_id', 'express_line_id'], 'dsp_coupon_usable_lines_coupon_id_express_line_id_index');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_coupon_usable_lines');
    }
};
