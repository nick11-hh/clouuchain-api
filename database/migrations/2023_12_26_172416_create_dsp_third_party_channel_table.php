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
        Schema::create('dsp_third_party_channel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('docking_type')->nullable()->comment('类型');
            $table->string('name', 191)->nullable()->default('')->comment('名称');
            $table->string('code', 191)->nullable()->default('')->comment('代码');
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
        Schema::dropIfExists('dsp_third_party_channel');
    }
};
