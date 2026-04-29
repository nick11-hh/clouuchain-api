<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditlineIdToDspUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('dsp_users', function (Blueprint $table) {
            $table->string('creditline_id')->nullable()->after('buyer_id')
                ->comment('40Seas账期ID');
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
            $table->dropColumn('creditline_id');
        });
    }
}
