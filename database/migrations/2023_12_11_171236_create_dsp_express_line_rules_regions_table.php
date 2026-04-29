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
        Schema::create('dsp_express_line_rules_regions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('rule_id')->index('dsp_express_line_rules_regions_rule_id_index');
            $table->bigInteger('region_id')->index('dsp_express_line_rules_regions_region_id_index');
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
        Schema::dropIfExists('dsp_express_line_rules_regions');
    }
};
