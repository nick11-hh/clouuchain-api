<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBuyerIdToDspUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_users', function (Blueprint $table) {
            $table->string('buyer_id', 255)->nullable()->comment('40Seas买家ID')->after('is_main');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_users', function (Blueprint $table) {
            $table->dropColumn('buyer_id');
        });
    }
}
