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
        Schema::create('dsp_rest_api_config', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('type')->nullable()->comment('API类型');
            $table->string('description')->nullable()->comment('描述');
            $table->tinyInteger('permission')->nullable()->comment('权限 1读 2写 3读写');
            $table->string('tag')->nullable()->comment('标签');
            $table->string('key')->nullable();
            $table->string('secret')->nullable();
            $table->bigInteger('admin_id')->nullable();
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
        Schema::dropIfExists('dsp_rest_api_config');
    }
};
