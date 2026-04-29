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
        Schema::create('dsp_express_line_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id')->index('dsp_express_line_rules_express_line_id_index')->comment('线路ID');
            $table->json('name')->comment('名称');
            $table->boolean('is_and')->comment('条件满足方式 0 任一满足 1 所有满足');
            $table->unsignedTinyInteger('type')->default(1)->comment('收费或者限制方式 1 按订单收费 2 按箱子收费 3 按单位重量收费 4 限制出仓');
            $table->unsignedTinyInteger('charge_mode')->default(1)->comment('计费方式 1 固定金额 2 申报价值比例 3 订单运费比例');
            $table->bigInteger('value')->comment('收费值 可能是金额或者百分比');
            $table->bigInteger('min_charge')->default(0)->comment('最低费用');
            $table->bigInteger('max_charge')->default(0)->comment('最高费用');
            $table->json('notice')->nullable()->comment('限制出仓提示');
            $table->string('condition', 191)->default('')->comment('规则条件');
            $table->string('result', 191)->default('')->comment('规则为真的结果');
            $table->string('else_result', 191)->default('')->comment('规则为假的结果');
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
        Schema::dropIfExists('dsp_express_line_rules');
    }
};
