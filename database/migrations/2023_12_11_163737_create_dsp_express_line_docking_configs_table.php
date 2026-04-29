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
        Schema::create('dsp_express_line_docking_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id');
            $table->bigInteger('start');
            $table->bigInteger('end');
            $table->unsignedInteger('docking_type');
            $table->string('channel_code', 191)->default('');
            $table->unsignedTinyInteger('push_type')->default(1);
            $table->boolean('third_push_now')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('express_line_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_line_docking_configs');
    }
};
