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
        Schema::create('dsp_express_line_labels_temp', function (Blueprint $table) {
            $table->bigInteger('express_line_id')->index('dsp_express_line_labels_temp_express_line_id_index')->comment('线路ID');
            $table->bigInteger('label_id')->index('dsp_express_line_labels_temp_label_id_index')->comment('标签ID');
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
        Schema::dropIfExists('dsp_express_line_labels_temp');
    }
};
