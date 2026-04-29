<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dsp_express_line_groups', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable();
            $table->tinyInteger('enabled')->default(1);
            $table->tinyInteger('only_for_group')->default(0);
            $table->tinyInteger('only_for_stg')->default(0)->comment('是否同行货专用：0-否 1-是');
            $table->softDeletes();
            $table->timestamps();
        });

        DB::statement("alter table `dsp_express_line_groups` comment '渠道路线分组表'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_express_line_groups');
    }
};
