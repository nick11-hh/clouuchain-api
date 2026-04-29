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
        Schema::create('dsp_express_line_region_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name');
            $table->json('reference_time');
            $table->boolean('enabled')->default(false);
            $table->bigInteger('template_id')->nullable()->index('dsp_express_line_region_templates_template_id_index');
            $table->unsignedTinyInteger('type')->default(1)->comment('分区类型');
            $table->bigInteger('country_id')->nullable()->comment('邮编分区国家 可为空');
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
        Schema::dropIfExists('dsp_express_line_region_templates');
    }
};
