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
        Schema::create('dsp_package_prop', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('cn_name')->nullable()->comment('属性中文名');
            $table->json('en_name')->nullable()->comment('属性英文名');
            $table->json('name')->nullable()->comment('属性名称');
            $table->integer('index')->default(0)->comment('排序');
            $table->string('color', 191)->nullable()->default('')->comment('颜色');
            $table->string('font_color', 191)->default('')->comment('字体颜色');
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
        Schema::dropIfExists('dsp_package_prop');
    }
};
