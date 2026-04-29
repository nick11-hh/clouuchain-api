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
        Schema::create('dsp_shop_groups_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('shop_group_id')->default(0)->comment('店铺分组ID')->index();
            $table->integer('shop_id')->default(0)->comment('店铺ID');
            $table->timestamps();
            $table->softDeletes();
            $table->comment('店铺分组映射表');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_shop_groups_mappings');
    }
};
