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
        Schema::create('dsp_admin_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('type')->nullable()->comment('日志类型')->index('type');
            $table->smallInteger('opt_type')->nullable()->comment('具体操作类型');
            $table->bigInteger('admin_id')->default(0)->nullable()->comment('操作管理员id');
            $table->bigInteger('custom_id')->default(0)->nullable()->comment('客户id')->index('custom_id');
            $table->string('description', 2000)->nullable()->comment('日志内容');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'deleted_at'], 'type_del_index');
            $table->index(['type', 'custom_id', 'deleted_at'], 'type_cid_del_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_admin_operation_logs');
    }
};
