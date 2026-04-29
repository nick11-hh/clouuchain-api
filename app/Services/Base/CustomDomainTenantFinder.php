<?php

namespace App\Services\Base;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;
use Spatie\Multitenancy\Models\Concerns\UsesTenantModel;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CustomDomainTenantFinder extends TenantFinder
{
    use UsesTenantModel;

    /**
     * @throws NoCurrentTenant
     */
    public function findForRequest(Request $request): ?Tenant
    {
        // 解析当前路由中的uuid并写入header中
        $this->analyzeUuid($request);

        // 白名单路由
        if ($tenant = $this->getWhiteListTenant($request)) return $tenant;

        // 通过前端域名确定租户, 兼容多请求头
        $domain = $request->header('Origin');
        if (empty($domain)) $domain = $request->get('uuid');
        if (empty($domain)) $domain = $request->header('origin');
        if (empty($domain)) $domain = $request->header('Referer');
        if (empty($domain)) $domain = $request->header(':Authority');

        if(empty($domain)){

            if($request->has('consumer_key') && $request->has('consumer_secret')){

                $domain = $request->getHost();

                $arr = explode('.', $domain);

                $domain2 = '.' . $arr[1] . '.' . $arr[2];

                $res = $this->getTenantModel()::where('status', 1)
                    ->where('admin_domain', 'like', "%" . $domain2)->first();

                return $res;
            }
        }

        $domain = trim($domain, '/');
        $domain = str_replace('http://', '', $domain);
        $domain = str_replace('https://', '', $domain);

        // 兼容带有path路径情况
        $domain = explode('/', $domain)[0] ?? '';
        if (empty($domain)) return null;
        $res = $this->getTenantModel()::where('status', 1)
            ->where(function ($query) use ($domain) {
                $query->where('client_domain', $domain)->orWhere('admin_domain', $domain)->orWhere('uuid', $domain);
            })->first();
//        if (!empty($res)) $request->merge(['uuid' => $res->uuid]);
        return $res;
    }

    /** 从path路由中解析出uuid并写入请求头（确定租户在路由服务注册之前，需要手动解析uuid）
     * @param Request $request
     * @return void
     */
    public function analyzeUuid(Request $request)
    {
        $path = '!' . trim($request->getPathInfo(), '/') . '!';
        $whitePathList = config('multitenancy.analyze_uuid_path');
        foreach ($whitePathList as $whitePath) {
            $whitePath = '!' . trim($whitePath, '/') . '!';
            $whitePath = str_replace('/', '\/', $whitePath);
            $pattern = '/' . str_replace('{uuid}', '(.*?)', $whitePath) . '/s';
            $matches = [];
            preg_match_all($pattern, $path, $matches);
            if (empty($matches[1]) || empty($matches[1][0])) continue;
            $request->headers->set('Origin', $matches[1][0]);
            break;
        }
    }

    /** 白名单path返回默认租户
     * @param $request
     * @return Tenant|bool
     * @throws NoCurrentTenant
     */
    public function getWhiteListTenant($request): Tenant|bool
    {
        // 白名单，默认返回第一个租户（解决telescope问题）
        $path = $request->getPathInfo();
        if (empty($path) || $path == '/') throw new NoCurrentTenant();
        $whiteList = config('multitenancy.white_list_path');
        $prefixWhiteList = config('multitenancy.prefix_white_list_path');

        if(str_contains($path, 'horizon')) {
            return $this->getDefaultTenant();
        }

        foreach ($whiteList as $whitePath) {  // 全等匹配路由
            if (trim($path, '/') === trim($whitePath, '/')) {
                return $this->getDefaultTenant();
            }
        }
        foreach ($prefixWhiteList as $whitePath) {  // 前缀匹配路由
            if (str_starts_with(trim($path, '/'), trim($whitePath, '/'))) {
                return $this->getDefaultTenant();
            }
        }
        return false;
    }

    public function getDefaultTenant()
    {
        return $this->getTenantModel()->when(config('multitenancy.white_list_path_default_tenant_id'), function ($query) {
            return $query->where('id', config('multitenancy.white_list_path_default_tenant_id'));
        })->first();
    }

}
