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
        Schema::table('dsp_purchase_plans', function (Blueprint $table) {
            $table->integer('status')->default(0)->comment('采购计划状态: 0-草稿 1-待采购 2-已处理')->change();
            $table->string('remark')->default('')->comment('备注');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_purchase_plans', function (Blueprint $table) {
            $table->integer('status')->comment('采购计划状态')->change();
            $table->dropColumn('remark');
        });
    }
};
