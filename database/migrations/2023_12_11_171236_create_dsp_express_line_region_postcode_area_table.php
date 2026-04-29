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
        Schema::create('dsp_express_line_region_postcode_area', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('region_id');
            $table->unsignedTinyInteger('type')->default(1)->comment('邮编规则');
            $table->string('start', 191);
            $table->string('end', 191)->default('')->comment('可以没有end');
            $table->index('region_id');
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
        Schema::dropIfExists('dsp_express_line_region_postcode_area');
    }
};
