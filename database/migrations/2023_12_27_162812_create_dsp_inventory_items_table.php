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
        Schema::create('dsp_inventory_items', function (Blueprint $table) {
            $table->comment('清点项目表');
            $table->bigIncrements('id');
            $table->string('name', 191)->comment('清点项目名');
            $table->integer('status')->default(0)->comment('清点项目状态 0-禁用 1-启用');
            $table->string('code', 191)->comment('清点项目代码');
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
        Schema::dropIfExists('dsp_inventory_items');
    }
};
