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
        Schema::create('dsp_goods_audit', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('goods_id')->index()->comment('商品ID');
            $table->tinyInteger('audit_status')->default(0)->comment('审核状态，0待审核1审核通过2审核拒绝');
            $table->bigInteger('audit_user_id')->nullable()->comment('审核人员id');
            $table->string('audit_user_name')->nullable()->comment('审核人员姓名');
            $table->timestamp('audit_time')->nullable()->comment('审核时间');
            $table->text('audit_remark')->nullable()->comment('审核备注');
            $table->tinyInteger('commit_status')->default(0)->comment('提交状态，0待提交审核1已提交审核');
            $table->bigInteger('commit_user_id')->nullable()->comment('提审人员id');
            $table->string('commit_user_name')->nullable()->comment('提审人员姓名');
            $table->timestamp('commit_time')->nullable()->comment('提审时间');
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
        Schema::table('dsp_goods_audit', function (Blueprint $table) {
            //
        });
    }
};
