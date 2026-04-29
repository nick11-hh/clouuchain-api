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
        Schema::create('dsp_express_line_quote', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('express_line_id')->comment('运费模板ID');
            $table->bigInteger('quote_id')->comment('报价模板ID');
            $table->softDeletes();

            $table->comment('运费模板报价关联表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_line_quote');
    }
};
