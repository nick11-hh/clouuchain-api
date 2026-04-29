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
        Schema::create('dsp_quotation_template', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable()->comment('模板名称');
            $table->json('cn_name')->nullable()->comment('中文模板名称');
            $table->json('en_name')->nullable()->comment('英文模板名称');
            $table->integer('prop_id')->default(0)->comment('属性ID');
            $table->string('prop_name')->comment('属性名称');
            $table->integer('sort')->default(100)->comment('排序');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用  0=禁用');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('mate物流报价模板表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_quotation_template');
    }
};
