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
        Schema::create('dsp_remote_types_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('condition_id')->comment('渠道规则条件ID');
            $table->bigInteger('remote_id')->comment('偏远类型ID');
            $table->index(['condition_id', 'remote_id'], 'dsp_remote_types_conditions_condition_id_remote_id_index');
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
        Schema::dropIfExists('dsp_remote_types_conditions');
    }
};
