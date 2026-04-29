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
        Schema::create('dsp_country_areas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('country_id')->index('dsp_country_areas_country_id_index');
            $table->json('name');
            $table->bigInteger('parent_id')->nullable()->index('dsp_country_areas_parent_id_index');
            $table->string('code', 191)->default('');
            $table->string('postcode', 191)->default('');
            $table->unsignedTinyInteger('enabled')->default(1);
            $table->string('api_code', 191)->default('')->comment('API值');
            $table->boolean('is_faraway')->default(false)->comment('是否偏远地区');
            $table->bigInteger('notification_id')->nullable()->comment('地域通知ID');
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
        Schema::dropIfExists('dsp_country_areas');
    }
};
