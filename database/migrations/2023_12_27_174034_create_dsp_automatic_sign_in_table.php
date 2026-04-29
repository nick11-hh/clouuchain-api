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
        Schema::create('dsp_automatic_sign_in', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('enabled')->default(0)->comment('状态 0-禁用 1-启用');
            $table->integer('trigger_days')->default(15)->comment('触发时间（天）');
            $table->tinyInteger('is_evaluate')->default(0)->comment('是否评价 0-不自动评价 1-自动评价');
            $table->integer('evaluate_score')->default(5)->comment('评价评分 0-5 分');
            $table->string('evaluate_content', 191)->default('')->comment('评价内容');
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
        Schema::dropIfExists('dsp_automatic_sign_in');
    }
};
