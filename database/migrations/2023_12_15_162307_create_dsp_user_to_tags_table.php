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
        Schema::create('dsp_user_to_tags', function (Blueprint $table) {
            $table->bigInteger('user_id')->index('dsp_user_to_tags_user_id_index');
            $table->bigInteger('tag_id')->index('dsp_user_to_tags_tag_id_index');
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
        Schema::dropIfExists('dsp_user_to_tags');
    }
};
