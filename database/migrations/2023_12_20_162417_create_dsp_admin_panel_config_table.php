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
        Schema::create('dsp_admin_panel_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('domain', 191);
            $table->json('title');
            $table->string('icon', 191);
            $table->json('login_title');
            $table->json('login_logo');
            $table->json('login_image');
            $table->json('sidebar_title');
            $table->json('sidebar_image');
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
        Schema::dropIfExists('dsp_admin_panel_config');
    }
};
