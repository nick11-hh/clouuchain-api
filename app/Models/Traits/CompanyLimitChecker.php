<?php

/**
 * @Author: h9471
 * @Created: 2020/2/26 14:43
 */

namespace App\Models\Traits;

use App\Lib\Code;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Exceptions\AccidentException;

/**
 * 公司版本限制检查
 * Trait CompanyLimitChecker
 * @package App\Models\Traits
 */
trait CompanyLimitChecker
{
    /**
     * 需要检查的路由
     * @var string[]
     */
    protected static $shouldCheck = [
        'admin/admins',
        'admin/warehouse-address',
        'admin/agents',
        'admin/express-lines',
    ];

    /**
     * 模型对应的检查字段
     * @var string[]
     */
    protected static $map = [
        'App\Models\Admin' => 'max_employee',
        'App\Models\Agent' => 'max_agent',
        'App\Models\ExpressLine' => 'max_express_line',
        'App\Models\WarehouseAddress' => 'max_warehouse',
    ];

    public static function bootCompanyLimitChecker()
    {
        if (self::routerChecker(\request(), self::$shouldCheck)) {
            static::saving(function ($model) {
                if (
                    static::groupMaximumLimit() !== 0
                    && static::query()->count('id') > static::groupMaximumLimit()
                ) {
                    throw new AccidentException('已达到当前版本的数据量限制，最大数量为:' . static::groupMaximumLimit(), Code::OPERATE_FAIL);
                }
            });
        }
    }

    /**
     * @return mixed
     */
    protected static function groupMaximumLimit()
    {
        if(empty(\auth()->user()->company->group)) {
            return 0;
        }

        $limit = \auth()->user()->company->group->getGroupMaximum();

        if (!$limit) {
            return 0;
        }

        return $limit[static::$map[static::class]];
    }

    /**
     * 路由检查器
     * @param Request $request
     * @param array $rules
     * @return bool
     */
    protected static function routerChecker(Request $request, array $rules)
    {
        return collect($rules)
            ->contains(function ($except) use ($request) {
                if ($except !== '/') {
                    $except = trim($except, '/');
                }

                //应该只有POST和PUT才需要检查
                return ($request->is('api/' . $except) || Str::startsWith($request->getPathInfo(), '/api/' . $except))
                    && ($request->method() === 'POST' || $request->method() === 'PUT');
            });
    }
}
