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
        Schema::create('dsp_express_line_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->index('dsp_express_line_services_express_line_id_index');
            $table->json('name')->comment('名称');
            $table->json('remark')->nullable()->comment('备注说明');
            $table->unsignedTinyInteger('type')->default(1)->comment('收取形式');
            $table->boolean('is_forced')->default(false)->comment('是否强制收取');
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
        Schema::dropIfExists('dsp_express_line_services');
    }
};
