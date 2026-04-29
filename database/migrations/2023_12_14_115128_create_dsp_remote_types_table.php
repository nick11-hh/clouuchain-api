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
        Schema::create('dsp_remote_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->comment('名称');
            $table->string('remark', 191)->nullable()->default('')->comment('备注');
            $table->tinyInteger('source')->nullable()->default(0)->comment('来源0-自定义1-系统内置');
            $table->bigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->string('operator_name', 191)->nullable()->default('')->comment('操作人名称');
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
        Schema::dropIfExists('dsp_remote_types');
    }
};
