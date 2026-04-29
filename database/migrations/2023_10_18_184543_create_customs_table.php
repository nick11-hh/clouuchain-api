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
        Schema::create('dsp_customs', function (Blueprint $table) {
            $table->id();
            $table->string('custom_name')->comment('客户主体名称')->index();
            $table->string('custom_phone')->nullable()->comment('客户主体电话')->index();
            $table->string('custom_email')->nullable()->comment('客户主体邮箱')->index();
            $table->tinyInteger('status')->default(1)->comment('客户主体状态')->index();
            $table->integer('group_id')->comment('客户主体所属分组')->index();
            $table->integer('main_user_id')->default(0)->comment('主体用户user_id');
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
        Schema::dropIfExists('dsp_customs');
    }
};
