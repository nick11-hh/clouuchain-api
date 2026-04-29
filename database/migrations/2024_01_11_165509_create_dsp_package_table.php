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
        Schema::create('dsp_package', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('express_num')->index('dsp_package_express_num_index')->comment('快递单号');
            $table->string('package_name')->nullable()->default('')->comment('包裹内容物名称');
            $table->string('package_value')->nullable()->default('')->comment('包裹价值 -- 分为单位');
            $table->string('sender_address', 191)->nullable()->default('')->comment('发件人地址');
            $table->tinyInteger('has_insurance')->nullable()->default(0)->comment('是否收取保费');
            $table->bigInteger('prop_id')->nullable()->comment('属性 id');
            $table->bigInteger('order_id')->nullable()->index('dsp_package_order_id_index')->comment('属性 id');
            $table->mediumInteger('status')->comment('包裹状态');
            $table->unsignedMediumInteger('sub_status')->default(0)->comment('子状态');
            $table->bigInteger('user_id')->index('dsp_package_user_id_index')->comment('包裹对应的用户');
            $table->bigInteger('package_volume_weight')->nullable()->comment('包裹预计重量');
            $table->bigInteger('package_weight')->nullable()->comment('包裹称重重量');
            $table->mediumInteger('length')->nullable()->comment('包裹长');
            $table->mediumInteger('width')->nullable()->comment('包裹宽');
            $table->mediumInteger('height')->nullable()->comment('包裹高');
            $table->string('location')->nullable()->default('')->index('dsp_package_location_index')->comment('货位');
            $table->unsignedInteger('location_suffix')->nullable()->comment('货位后缀');
            $table->json('item_pictures')->nullable()->comment('包裹明细图片');
            $table->json('package_pictures')->nullable()->comment('包裹入库拍照图片');
            $table->string('remark')->nullable()->comment('备注');
            $table->bigInteger('operator_id')->nullable()->comment('入库操作人ID');
            $table->string('in_storage_remark')->nullable()->comment('入库备注');
            $table->unsignedTinyInteger('not_confirmed')->default(0)->comment('是否未被用户确认，即直接从后台添加的');
            $table->timestamp('in_storage_at')->nullable()->comment('入库时间');
            $table->timestamp('received_at')->nullable()->comment('仓库签收时间');
            $table->timestamp('weighed_at')->nullable()->comment('仓库称重时间');
            $table->timestamps();
            $table->bigInteger('express_id')->nullable()->default(0)->comment('对应的快递公司id');
            $table->bigInteger('country_id')->default(0)->comment('包裹寄往的国家,如不填,代表所有');
            $table->bigInteger('warehouse_id')->default(0)->comment('包裹寄往的仓库,如不填,代表默认');
            $table->bigInteger('category_id')->default(0)->comment('包裹分类');
            $table->bigInteger('qty')->default(1)->comment('包裹的商品数量');
            $table->json('operate_logs')->nullable()->comment('包裹操作日志');
            $table->dateTime('invalid_at')->nullable()->comment('作废时间');
            $table->json('contact_info')->nullable()->comment('联系信息');
            $table->bigInteger('express_line_id')->nullable()->comment('线路ID');
            $table->bigInteger('payment_id')->nullable()->comment('支付方式');
            $table->unsignedTinyInteger('mode')->default(0)->comment('包裹转运模式');
            $table->unsignedTinyInteger('payment_mode')->nullable()->comment('支付模式');
            $table->json('added_service')->nullable()->comment('单票转运增值服务');
            $table->bigInteger('batch_id')->nullable()->comment('包裹提交批次');
            $table->json('additional_info')->nullable()->comment('额外信息');
            $table->bigInteger('group_buying_id')->nullable()->comment('团购ID');
            $table->integer('number')->nullable()->default(1)->comment('件数');
            $table->unsignedTinyInteger('is_warning')->default(0)->index('dsp_package_is_warning_index')->comment('是否未入库预警包裹 0：否 1：是');
            $table->string('code', 191)->nullable()->comment('包裹识别码');
            $table->unsignedTinyInteger('ship_mode')->default(0);
            $table->bigInteger('channel_id')->nullable();
            $table->boolean('ignore_claim')->default(false)->index('dsp_package_ignore_claim_index');
            $table->boolean('is_exceptional')->default(false)->comment('是否异常件');
            $table->string('exceptional_remark')->default('')->comment('异常件备注');
            $table->unsignedTinyInteger('source')->default(0)->comment('包裹来源 0 客户预报 1 后台添加');
            $table->boolean('is_claimed')->default(false);
            $table->string('r_express_num')->nullable()->virtualAs('reverse(`express_num`)')->index('dsp_package_r_express_num_index');
            $table->unsignedTinyInteger('pick_status')->default(0);
            $table->tinyInteger('is_show')->nullable()->default(1)->comment('是否显示0-否1-是');
            $table->tinyInteger('tracking_type')->nullable()->comment('第三方物流类型1-快递100;2-51tracking;3-17track');
            $table->string('third_tracking_status', 191)->nullable()->default('')->comment('第三方物流状态');
            $table->string('reserve_box_number_id', 191)->nullable()->default('')->comment('预留分箱号ID');
            $table->integer('is_spu')->default(0)->comment('是否是spu包裹');
            $table->bigInteger('parent_id')->nullable()->comment('父包裹id');
            $table->integer('submitted')->default(0)->comment('已提交数量');
            $table->tinyInteger('daigou')->nullable()->default(0)->comment('是否代购0-否1-是');
            $table->bigInteger('container_id')->nullable()->comment('货柜号ID');
            $table->tinyInteger('scale')->nullable()->comment('包裹大小1-小2-大');
            $table->softDeletes();

            $table->index('group_buying_id');
            $table->unique('code');
            $table->unique('express_num');
            $table->fullText(['package_name', 'location', 'remark', 'code']);
            $table->index(['is_show', 'status']);
            $table->index('is_spu');
            $table->index('parent_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dsp_package');
    }
};
