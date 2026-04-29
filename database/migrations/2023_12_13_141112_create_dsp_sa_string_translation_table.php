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
        Schema::create('dsp_sa_string_translation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 512)->comment('翻译对应的 key');
            $table->json('translation')->nullable()->comment('key 对应的多语种翻译');
            $table->tinyInteger('source')->default(0)->comment('来源');
            $table->index(['source', 'key'], 'source_key_index');
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
        Schema::dropIfExists('dsp_sa_string_translation');
    }
};
