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
        Schema::create('dsp_agent_commission_template_config_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('config_id');
            $table->bigInteger('express_line_id');
            $table->unsignedTinyInteger('type')->default(1);
            $table->bigInteger('value');
            $table->index(['config_id', 'express_line_id'], 'config_id_express_line_id_index');
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
        Schema::dropIfExists('dsp_agent_commission_template_config_lines');
    }
};
