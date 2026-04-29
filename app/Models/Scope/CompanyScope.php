<?php

/**
 * @Author: h9471
 * @Created: 2019/10/21 17:07
 */

namespace App\Models\Scope;

use App\Models\Admin;
use App\Models\AdminPanelConfig;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Cache;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder  $builder
     * @param  Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        //如果是管理员端
        if ($user instanceof Admin) {
            $builder->whereRaw($model->getTable() . '.company_id = ' . $user->company_id);
        }

        //如果是客户端
        if ($user instanceof User) {
            $builder->whereRaw($model->getTable() . '.company_id = ' . $user->company_id);
            return;
        }

        //如果是未认证状态
        if (auth()->guest()) {
            if (!($id = static::getUuidFromHeader())) {
                $id = static::getUuidFromInput();

                if (!$id) {
                    $id = static::getUuidByDomain();
                }
            }

            if ($id) {
                $builder->whereRaw($model->getTable() . '.company_id = ' . $id);
            } else {
                // 客户端的UUID错误的或者没传的数据为空
                //if (str_starts_with(request()->path(), 'api/client')) {
                //    $builder->whereRaw($model->getTable() . '.company_id = -1');
                //}
            }
        }
    }

    /**
     * @return int|null
     */
    public static function getUuidFromHeader(): ?int
    {
        if (request()->hasHeader('X-Uuid')) {
            $uuid = request()->header('X-Uuid');

            if (! $uuid) {
                return null;
            }

            return Cache::get($uuid, function () use ($uuid) {
                $admin = Admin::where('uuid', $uuid)->withoutGlobalScope(CompanyScope::class)->first();

                if ($admin) {
                    $id = $admin->id;
                } else {
                    $id = 0;
                }

                Cache::forever($uuid, $id);

                return $id;
            });
        }

        return null;
    }

    /**
     * @return int|null
     */
    public static function getUuidFromInput(): ?int
    {
        if (request()->has('X-Uuid')) {
            $uuid = request()->input('X-Uuid');

            return Cache::get($uuid, function () use ($uuid) {
                $id = Admin::where('uuid', $uuid)->withoutGlobalScope(CompanyScope::class)->firstOrFail()->id;

                Cache::forever($uuid, $id);

                return $id;
            });
        }

        return null;
    }

    /**
     * @return int|null
     */
    public static function getUuidByDomain(): ?int
    {
        if (request()->hasHeader('Origin')) {
            $origin = request()->header('Origin');

            $domain = parse_url($origin, PHP_URL_HOST);

            if (str_contains($domain, 'haiouoms.com')) {
                return null;
            }

            $customDomain = AdminPanelConfig::query()
                ->withoutGlobalScopes()
                ->where('domain', $domain)
                ->first();

            if ($customDomain) {
                return $customDomain->company_id;
            }
        }

        return null;
    }
}
