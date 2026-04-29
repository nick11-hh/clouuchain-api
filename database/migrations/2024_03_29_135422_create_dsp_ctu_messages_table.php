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
        Schema::create('dsp_ctu_messages', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->default(1)->nullable()->comment('类型1-全体客户2-客户组3-指定客户');
            $table->json('title')->comment('标题');
            $table->json('content')->comment('内容');
            $table->tinyInteger('status')->default(0)->nullable()->comment('状态0-未发布1-已发布');
            $table->integer('read_count')->default(0)->nullable()->comment('已读数量');
            $table->integer('total_count')->default(0)->nullable()->comment('总数量');
            $table->bigInteger('operator_id')->comment('操作人ID');
            $table->string('operator')->comment('操作人名称');
            $table->softDeletes();
            $table->timestamps();
            $table->comment('消息通知表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_ctu_messages');
    }
};
