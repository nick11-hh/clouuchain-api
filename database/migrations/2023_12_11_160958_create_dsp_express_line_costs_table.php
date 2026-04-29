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
        Schema::create('dsp_express_line_costs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name')->nullable()->comment('费用名称');
            $table->json('remark')->nullable()->comment('费用备注');
            $table->unsignedTinyInteger('type')->default(0)->comment('费用计算类型， 0 固定金额 1 按运费比例');
            $table->bigInteger('price')->default(0)->comment('费用价格');
            $table->bigInteger('proportion')->default(0)->comment('费用比例');
            $table->unsignedTinyInteger('enabled')->default(1)->comment('是否开启');
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
        Schema::dropIfExists('dsp_express_line_costs');
    }
};
