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
        Schema::create('dsp_sa_languages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->unique('dsp_sa_languages_name_unique')->comment('语言名字 -- 管理员端定制名字');
            $table->string('language_code', 191)->unique('dsp_sa_languages_language_code_unique')->comment('语言代码 -- 关联');
            $table->mediumInteger('index')->default(999)->comment('排序值');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认');
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
        Schema::dropIfExists('dsp_sa_languages');
    }
};
