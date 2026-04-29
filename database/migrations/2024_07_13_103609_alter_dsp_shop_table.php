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
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->string('tax')->default('')->comment('店铺税号');
            $table->string('european_union_tax')->default('')->comment('欧盟税号');
            $table->string('united_kingdom_tax')->default('')->comment('英国税号');
            $table->string('norway_tax')->default('')->comment('挪威税号');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('dsp_shop', function (Blueprint $table) {
            $table->dropColumn([
                                   'tax',
                                   'european_union_tax',
                                   'united_kingdom_tax',
                                   'norway_tax',
                               ]);
        });
    }
};
