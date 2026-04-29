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
        Schema::table('dsp_email_template', function (Blueprint $table) {
            $table->string('title')->nullable()->comment('邮件标题')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_email_template', function (Blueprint $table) {
            $table->json('title')->nullable()->comment('邮件标题')->change();
        });
    }
};
