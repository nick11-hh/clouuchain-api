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
        Schema::create('dsp_logistics_channel', function (Blueprint $table) {
            $table->id();
            $table->integer('express_companies_id')->default(0)->comment('物流公司id');
            $table->string('code')->default('')->comment('渠道编码');
            $table->string('name')->default('')->comment('渠道名称');
            $table->tinyInteger('enable')->default(0)->comment('状态：0-未启用 1-启用');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_logistics_channel');
    }
};
