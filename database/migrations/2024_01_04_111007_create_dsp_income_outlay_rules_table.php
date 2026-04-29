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
        Schema::create('dsp_income_outlay_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->default('')->index('dsp_income_outlay_rules_code_index')->comment('编码');
            $table->string('name', 100)->default('')->comment('规则名称');
            $table->unsignedTinyInteger('resource_type')->nullable()->default(1)->comment('分类名称1-成长值2-积分');
            $table->unsignedTinyInteger('type')->nullable()->default(1)->comment('收支类型1-收入2-支出');
            $table->string('remark')->nullable()->default('')->comment('备注');
            $table->unsignedTinyInteger('enabled')->nullable()->default(1)->comment('状态1-开启0-禁用');
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
        Schema::dropIfExists('dsp_income_outlay_rules');
    }
};
