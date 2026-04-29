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
        Schema::create('dsp_user_addresses_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('address_id')->comment('地址ID');
            $table->bigInteger('tag_id')->comment('标签ID');
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
        Schema::dropIfExists('dsp_user_addresses_tags');
    }
};
