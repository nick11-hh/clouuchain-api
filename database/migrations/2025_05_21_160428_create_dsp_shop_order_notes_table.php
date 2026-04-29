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
        Schema::create('dsp_shop_order_notes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shop_order_id')->comment('店铺订单id');
            $table->string('note', 500)->nullable()->comment('订单备注');
            $table->bigInteger('user_id')->nullable()->default(0)->comment('创建人');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_order_id', 'deleted_at'], 'oid_del_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_order_notes');
    }
};
