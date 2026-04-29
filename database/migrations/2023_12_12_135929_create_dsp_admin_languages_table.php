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
        Schema::create('dsp_admin_languages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->nullable()->comment('语言名字 -- 管理员端定制名字');
            $table->string('language_code', 191)->comment('语言代码 -- 关联超管端语言');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认');
            $table->tinyInteger('enabled')->default(1)->comment('是否开启');
            $table->bigInteger('company_id')->comment('对应的公司标识');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'language_code'], 'dsp_admin_languages_company_id_language_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_admin_languages');
    }
};
