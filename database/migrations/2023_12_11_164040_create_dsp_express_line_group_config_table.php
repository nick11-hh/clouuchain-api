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
        Schema::create('dsp_express_line_group_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('express_line_id');
            $table->unsignedTinyInteger('is_group')->default(0)->comment('是否团购线路');
            $table->boolean('mode')->default(false)->comment('拼团支付模式');
            $table->boolean('group_raise')->default(false)->comment('团购加价');
            $table->unsignedBigInteger('group_raise_threshold')->default(0)->comment('团购加价阈值');
            $table->decimal('group_raise_factor', 10)->default(1.1)->comment('团购加价系数 默认1.1倍');
            $table->bigInteger('type')->default(0)->comment('拼团类型 0 公开与私密 1 公开 2 私密');
            $table->boolean('weight_limit')->default(false)->comment('是否开启重量限制 0 关闭 1 开启');
            $table->bigInteger('weight_threshold')->default(0)->comment('重量限制门槛');
            $table->integer('is_ignore_warehouse')->nullable()->default(0)->comment('是否开启配置仓库');
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
        Schema::dropIfExists('dsp_express_line_group_config');
    }
};
