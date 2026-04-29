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
        Schema::create('dsp_third_party_tracking_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('declare_id')->default()->comment('申报ID');
            $table->text('content')->nullable()->comment('内容');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_third_party_tracking_logs');
    }
};
