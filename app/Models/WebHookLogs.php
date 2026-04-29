<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Model;


/**
 * App\Models\WebHookLogs
 *
 * @property int $id
 * @property string $platform 平台标识
 * @property string $external_id webhook外部平台的唯一标识符
 * @property string $event_type 事件类型
 * @property mixed|null $request_headers 请求头
 * @property mixed|null $request_body 完整数据
 * @property int $status 处理状态：1=pending，2=succeed，3=fail
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs pageSize(int $size = 10)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs query()
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereRequestBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereRequestHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WebHookLogs whereWhen($column, $value, $operator = '=')
 * @mixin \Eloquent
 */
class WebHookLogs extends Model
{
    use Basis;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    // 状态待处理
    const STATUS_PENDING = 1;
    // 状态成功
    const STATUS_SUCCESS = 2;
    // 状态失败
    const STATUS_FAIL = 3;

    protected $table = 'webhook_logs';
}
