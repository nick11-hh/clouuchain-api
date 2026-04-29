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
        Schema::create('dsp_picking_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('warehouse_id')->comment('仓库id');
            $table->string('picking_sn')->comment('拣货单号');
            $table->tinyInteger('type')->comment('拣货单类型');
            $table->bigInteger('picking_staff_id')->comment('拣货单人员');
            $table->tinyInteger('is_print')->default(0)->comment('拣货单是否打印');
            $table->tinyInteger('status')->default(1)->comment('状态 1 待拣货 2 拣货中 3 拣货完成');
            $table->timestamp('complete_time')->nullable()->comment('完成时间');
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
        Schema::dropIfExists('dsp_picking_orders');
    }
};
