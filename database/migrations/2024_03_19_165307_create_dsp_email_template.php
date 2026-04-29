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
        Schema::create('dsp_email_template', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('type')->default(0)->comment('模板类型');
            $table->json('title')->nullable()->comment('邮件标题');
            $table->json('content')->nullable()->comment('邮件内容');
            $table->tinyInteger('enabled')->default(0)->comment('是否启用');
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
        Schema::dropIfExists('dsp_email_template');
    }
};
