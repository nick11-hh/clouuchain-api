<?php

namespace App\Services\Base;

use App\Lib\Code;
use App\Models\SystemConfig;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use App\Models\SystemConfigOperateLogs;
use App\Exceptions\AccidentException;

class SystemConfigService
{

    /** 批量设置配置项
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public static function batchSet($data)
    {
        foreach ($data as $key => $value) {
            if (!isset(SystemConfig::DEFAULT_VALUE[$key])) continue;

            //校验cos配置
            if ($key === SystemConfig::COS_CONFIG && !empty($value['app_id'])) {
                self::verifyCosConfig($value);
            }

            self::setConfig($key, $value);
        }
        return true;
    }

    /** 设置配置项
     * @param $key
     * @param $value
     * @param $ext
     * @return void
     * @throws \Exception
     */
    public static function setConfig($key, $value, $ext = null)
    {
        if (!isset(SystemConfig::DEFAULT_VALUE[$key])) throw new AccidentException('未定义的配置项，请先定义', Code::OPERATE_FAIL);
        $config = SystemConfig::query()->where('config_key', $key)->first();
        if (empty($config)) {
            $config = new SystemConfig();
            $config->config_key = $key;
        }
        $oldValue = $config->config_value;

        $config->config_value = $value ?? '';
        $config->ext = $ext;
        $config->save();


        //写入操作日志，目前值写入系统配置项相关 并且已修改的
        $systemConfigList = SystemConfig::systemConfigList();
        if (in_array($key, array_keys($systemConfigList)) && $value != $oldValue) {

            $valueList = SystemConfig::systemConfigValueList();

            if($key == SystemConfig::QUOTE_FAVOURABLE_SETTING){

                $oldValueDesc   = $valueList[$key][$oldValue['is_open']] ?? $oldValue['is_open'];
                $ValueDesc      = $valueList[$key][$value['is_open']] ?? $value['is_open'];

                $logData['content'] = '';
                if($oldValueDesc != $ValueDesc){

                    $logData['content'] = '报价设置项【'. $systemConfigList[$key] . '】从 '. $oldValueDesc . ' 修改为 ' . $ValueDesc . '；';

                }

                if($oldValue['price'] != $value['price']){

                    if(empty($logData['content'])){
                        $logData['content'] = '报价设置项 ';
                    }

                    $logData['content'] = $logData['content'] . '优惠金从' . $oldValue['price'] . '更新为' . $value['price'];

                }

                //写入操作日志
                SystemConfigOperateLogs::saveLog($logData);

            }else{

                $oldValueDesc   = $valueList[$key][$oldValue] ?? $oldValue;
                $ValueDesc      = $valueList[$key][$value] ?? $value;

                $prefix = '系统配置项';
                if(in_array($key, SystemConfig::quoteConfigList())){

                    $prefix = '报价设置项';
                }

                $logData = [
                    'content' => $prefix . '【'. $systemConfigList[$key] . '】从 '. $oldValueDesc . ' 修改为 ' . $ValueDesc,
                ];
                //写入操作日志
                SystemConfigOperateLogs::saveLog($logData);
            }
        }

    }

    /** 获取配置项
     * @param $key
     * @return mixed
     */
    public static function getConfigValue($key)
    {
        $config = SystemConfig::query()->where('config_key', $key)->first();
        if (empty($config)) {
            return SystemConfig::DEFAULT_VALUE[$key];
        }
        return $config->config_value;
    }

    /** 获取多个key的配置项
     * @param array $keyArr
     * @return array
     */
    public static function getMultipleConfig(array $keyArr): array
    {
        $configList = SystemConfig::query()->whereIn('config_key', $keyArr)->get()->keyBy('config_key');
        $result = [];
        foreach ($keyArr as $key) {
            if (isset($configList[$key])) {
                $value = $configList[$key]->config_value;
            } else {
                if (!isset(SystemConfig::DEFAULT_VALUE[$key])) continue;
                $value = SystemConfig::DEFAULT_VALUE[$key];
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /**
     * 校验cos配置
     */
    public static function verifyCosConfig(array $params): bool
    {
        validator($params, [
            'app_id' => 'required|int',
            'secret_id' => 'required|string',
            'secret_key' => 'required|string',
            'region' => 'required|string',
            'bucket' => 'required|string',
        ])->validate();

        $cosKey = 'CACHE_COS_CONFIG:TENANT_UUID-' . getCurrentUuid();

        try {
            self::setCosConfig($params);

            //测试上传功能
            $name = date('YmdHis') . '-cos-test.png';
            Storage::disk()
                   ->putFileAs(
                       'admin',
                       'https://jiyun-dev-1314883188.cos.ap-hongkong.myqcloud.com/admin/20240904-7ZBqYjwEjpnG8TFn.png',
                       $name,
                   );

            //校验通过 添加缓存
            Cache::set($cosKey, $params);
        } catch (Exception $e) {
            //校验失败 删除历史缓存
            Cache::delete($cosKey);

            throw new AccidentException('授权失败:' . $e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    public static function setCosConfig($cosConfig = []): bool
    {
        if (empty($cosConfig)) {
            $cosKey = 'CACHE_COS_CONFIG:TENANT_UUID-' . getCurrentUuid();

            $cosConfig = Cache::get($cosKey) ?: self::getConfigValue(SystemConfig::COS_CONFIG);

            //添加缓存
            if (!Cache::has($cosKey)) Cache::set($cosKey, $cosConfig);
        }

        //启用时使用客户的COS，不设置则使用ENV的配置
        if ($cosConfig['enable']) {
            foreach ($cosConfig as $key => $value) {
                Config::set('filesystems.disks.cos.'.$key, $value);
            }
        }

        return true;
    }

}
