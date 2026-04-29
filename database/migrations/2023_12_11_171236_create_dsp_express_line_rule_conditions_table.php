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
        Schema::create('dsp_express_line_rule_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id');
            $table->bigInteger('rule_id')->index('dsp_express_line_rule_conditions_rule_id_index');
            $table->unsignedTinyInteger('param')->comment('比较参数');
            $table->string('comparison', 191)->comment('比较符号');
            $table->bigInteger('value')->comment('比较值');
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
        Schema::dropIfExists('dsp_express_line_rule_conditions');
    }
};
