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
        Schema::create('dsp_company_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->comment('公司组名称');
            $table->unsignedInteger('company_count')->default(0)->comment('公司数');
            $table->string('remark', 191)->default('')->comment('备注');
            $table->unsignedInteger('max_employee')->default(0)->comment('最大员工数量');
            $table->unsignedInteger('max_warehouse')->default(0)->comment('最大仓库数量');
            $table->unsignedInteger('max_express_line')->default(0)->comment('最大线路数量');
            $table->unsignedInteger('max_agent')->default(0)->comment('最大代理数量');
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
        Schema::dropIfExists('dsp_company_groups');
    }
};
