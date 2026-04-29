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
        Schema::create('dsp_quotation_template_props', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('quotation_id')->comment('报价模板ID');
            $table->bigInteger('prop_id')->comment('属性ID');
            $table->softDeletes();

            $table->comment('报价属性关联表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_quotation_template_props');
    }
};
