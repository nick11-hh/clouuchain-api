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
        Schema::create('dsp_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('部门名称');
            $table->integer('level')->comment('部门层级');
            $table->bigInteger('parent_id')->comment('父级id');
            $table->text('description')->nullable()->comment('描述');
            $table->tinyInteger('status')->default(1)->comment('状态 1 启用 0 禁用');
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
        Schema::dropIfExists('dsp_departments');
    }
};
