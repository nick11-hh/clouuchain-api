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
        Schema::create('dsp_user_address', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->index('dsp_user_address_user_id_index')->comment('地址对应的用户');
            $table->string('receiver_name')->comment('收件人姓名');
            $table->string('timezone')->default('0031')->comment('联系电话时区');
            $table->string('phone')->comment('收件人电话');
            $table->bigInteger('country_id')->comment('收件人国家 id');
            $table->string('city')->comment('收件人城市');
            $table->string('street')->comment('收件人街道');
            $table->string('door_no', 191)->nullable()->default('')->comment('收件人门牌号');
            $table->string('postcode')->comment('收件人邮编');
            $table->string('address', 512)->nullable()->default('')->comment('详细地址,选填项');
            $table->string('clearance_code')->nullable()->default('')->comment('清关编码');
            $table->string('line_extra_remark')->nullable()->default('')->comment('线路额外收录信息');
            $table->string('id_card', 191)->default('')->comment('身份证号');
            $table->string('wechat_id', 191)->nullable()->comment('Wechat ID');
            $table->string('area', 191)->default('')->comment('区域');
            $table->string('province', 191)->default('');
            $table->string('state', 191)->default('');
            $table->string('district', 191)->default('');
            $table->unsignedTinyInteger('is_cn_address')->default(0);
            $table->bigInteger('area_id')->nullable();
            $table->bigInteger('sub_area_id')->nullable();
            $table->string('email', 191)->default('')->comment('邮箱');
            $table->boolean('is_default')->default(false)->comment('是否默认地址');
            $table->unsignedTinyInteger('is_invalid')->nullable()->default(0)->comment('是否失效：0否，1是');
            $table->string('station_code', 191)->default('')->comment('站点码');
            $table->tinyInteger('address_type')->nullable()->default(1)->comment('地址类型1-送货上门2-自提点');
            $table->bigInteger('station_id')->nullable()->comment('自提点ID');
            $table->string('spare_phone', 191)->nullable()->default('')->comment('备用电话');
            $table->tinyInteger('status')->default(1)->comment('审核状态 0 待审核 1 审核通过 2 审核不通过');
            $table->bigInteger('low_area_id')->nullable()->comment('三级区域ID');
            $table->string('company', 191)->default('')->comment('公司名称');
            $table->json('custom_tags')->nullable()->comment('自定义标签');
            $table->bigInteger('warehouse_id')->nullable()->comment('仓库ID');
            $table->tinyInteger('warehouse_default')->nullable()->default(0)->comment('仓库默认地址');
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
        Schema::dropIfExists('dsp_user_address');
    }
};
