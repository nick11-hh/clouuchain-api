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
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE dsp_shop_order ADD COLUMN deleted_at_index VARCHAR ( 255 )
    GENERATED ALWAYS AS (COALESCE ( deleted_at, '1970-01-01 00:00:00' )) STORED");

            Schema::table('dsp_shop_order', function (Blueprint $table) {
                $table->unique(['order_id', 'shop_id', 'deleted_at_index']);
            });
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop_order', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'shop_id', 'deleted_at_index']);

            $table->dropColumn(['deleted_at_index']);
        });
    }
};
