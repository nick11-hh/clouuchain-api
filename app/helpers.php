<?php

/**
 * 雪花算法生成uuid
 */

use App\Models\Landlord\Tenant;
use App\Models\Landlord\Tenants;
use Illuminate\Support\Facades\Cache;

if (!function_exists('uuid')) {
    function uuid(): string
    {
        $uuid = sprintf('%04x%04x%04x%04x%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // 将 UUID 转换成 13 位纯数字
        $uuid_number = str_replace('-', '', $uuid);
        $uuid_number = base_convert($uuid_number, 16, 10);
        $merchant_id = substr($uuid_number, 0, 13);

        $model = new Tenants();

        $merchant = $model->where('domain', $merchant_id)->value('id');
        if ($merchant) {
            return uuid();
        }

        return $merchant_id;
    }
}

if (!function_exists('getUserId')) {
    function getUserId()
    {
        return auth('client')->user()->id ?? 0;
    }
}

if (!function_exists('getCustomId')) {
    function getCustomId()
    {
        return auth('client')->user()->custom_id ?? 0;
    }
}

if (!function_exists('getAdminId')) {
    function getAdminId()
    {
        return auth('admin')->user()->id ?? 0;
    }
}

if (!function_exists('transformArray')) {
    function transformArray($data)
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = [
                'value' => $key,
                'label' => $value,
            ];
        }
        return $result;
    }
}

// 返回32位唯一码
if (!function_exists('getUuid')) {
    function getUuid()
    {
        $code = md5(strtoupper(uniqid()) . rand(10000, 99999));
        $redisKey = "uuid_unique_code_{$code}";
        $isExist = \Illuminate\Support\Facades\Cache::get($redisKey);
        if ($isExist) getUuid();
        \Illuminate\Support\Facades\Cache::put($redisKey, 1, \Illuminate\Support\Carbon::now()->addSeconds(2));
        return $code;
    }
}

if (!function_exists('starts_with')) {
    /**
     * @param $string
     * @param $start
     * @return bool
     */
    function starts_with($string, $start)
    {
        return \Illuminate\Support\Str::startsWith($string, $start);
    }
}

if (!function_exists('number2Chs')) {
    /**
     * @param int|string $number
     * @return string
     */
    function number2Chs(int|string $number): string
    {
        $map = [
            '零' => 0, '一' => 1, '二' => 2, '三' => 3, '四' => 4,
            '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9
        ];

        return str_replace(array_values($map), array_keys($map), $number);
    }
}

if (!function_exists('admin_path')) {
    function admin_path(string $path = '')
    {
        $path = ltrim($path, '/');

        return sprintf('admin/%s', $path);
    }
}

/**
 * 计费重量小于100g向下取整
 */
if (!function_exists('_roundDownTo')) {
    /**
     * @param $weight
     * @param $ceil
     * @return int
     */
    function _roundDownTo($weight, $down = 100)
    {
        if ($weight < 1000) {
            return $weight;
        }
        $tmp = floor($weight / 1000) * 1000;
        if ($weight - $tmp <= $down) {
            $weight = $tmp;
        }
        return $weight;
    }
}

/**
 * 计费重量上浮函数
 */
if (!function_exists('_ceilTo')) {
    /**
     * @param $weight
     * @param $ceil
     * @return int
     */
    function _ceilTo($weight, $ceil)
    {
        $kgWeight = bcdiv($weight, 1000, 6);

        if ($ceil == 0) {
            return $weight;
        }

        if ($ceil == 1) {
            return (int)bcmul(ceil($kgWeight), 1000);
        }

        if ($ceil == 0.5) {
            $tmp = floor($kgWeight);

            if ($tmp == $kgWeight || $tmp + $ceil == $kgWeight) {
                return $weight;
            }

            if ($tmp + $ceil < $kgWeight) {
                return (int)bcmul(ceil($kgWeight), 1000);
            } elseif ($tmp + $ceil > $kgWeight) {
                return (int)bcmul(bcadd($tmp, 0.5, 1), 1000);
            }
        }

        if ($ceil == 0.05) {
            return (int)(_ceilTo($weight * 10, 0.5) / 10);
        }

        $tmp = floor($kgWeight);
        info('当前数据', [$kgWeight, $tmp]);

        $kSubT = bcsub($kgWeight, $tmp, 6);
        //bcadd 相加 bcsub 相减 bcmode 取余数 bcdiv 相除 bcmul 相乘 bcpow 幂运算
        $div = floor($kSubT / $ceil);
        $mod = bcmod($kSubT, $ceil, 3);

        if ($tmp == $kgWeight || $mod == 0) {
            return $weight;
        }
        // 精确带到小数点后两位的上浮
        return (int)bcmul(bcadd($tmp, $ceil * ($div + 1), 2), 1000);
    }
}

if (!function_exists('postcode_integer')) {
    function postcode_integer(string|null $postcode = '')
    {
        preg_match_all('/\d+/', $postcode ?? '', $match);

        return ($match[0] ?? null) ? collect($match[0])->max() : 0;
    }
}

if (!function_exists('isZh')) {
    /**
     * @return bool
     */
    function isZh()
    {
        return str_contains(app('request')->header('language'), 'zh') || app()->isLocale('zh_CN');
    }
}

if (!function_exists('isEn')) {
    /**
     * @return bool
     */
    function isEn()
    {
        return str_contains(app('request')->header('language'), 'en') || app()->isLocale('en_US');
    }
}

if (!function_exists('isRu')) {
    /**
     * 是否是俄语
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:05
     */
    function isRu()
    {
        return str_contains(app('request')->header('language'), 'ru') || app()->isLocale('ru_RU');
    }
}

if (!function_exists('isAr')) {
    /**
     * 是否是阿拉伯语
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:05
     */
    function isAr()
    {
        return str_contains(app('request')->header('language'), 'ar') || app()->isLocale('ar_SA');
    }
}

if (!function_exists('isPt')) {
    /**
     * 是否是葡萄牙语
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:05
     */
    function isPt()
    {
        return str_contains(app('request')->header('language'), 'pt') || app()->isLocale('pt_PT');
    }
}

if (!function_exists('isVi')) {
    /**
     * 是否是越南语
     * @return bool
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:05
     */
    function isVi()
    {
        return str_contains(app('request')->header('language'), 'vi') || app()->isLocale('vi_VN');
    }
}

/**
 * 汇率转换
 */
if (!function_exists('rate_transform')) {
    /**
     * @param $number
     * @param $rate
     * @param int $decimals
     * @return string
     */
    function rate_transform($number, $rate, $decimals = 2)
    {
        return number_format(
            bcmul($number, $rate, $decimals + 1),
            $decimals,
            '.',
            ''
        );
    }
}

if (!function_exists('generateOrderId')) {
    /**
     * @desc 系统订单号
     * 生成规则：CO+固定四位客户id+年月日+四位自增数字(每日重置) CO00012404290001
     * @return string
     */
    function generateOrderId(int $customerId = 0)
    {
        $pre = 'CO';
        $customerId = $customerId ?: getCustomId();
        $date = date('ymd');
        $cacheKey = 'systemOrderNumber:' . $customerId . ':' . $date;

        if (Cache::has($cacheKey)) {
            $increment = Cache::increment($cacheKey);
        } else {
            // 获取当前的时间戳（秒）
            $currentTimestamp = time();

            // 获取今天的结束时间戳（秒）
            $todayEndTimestamp = strtotime("tomorrow") - 1;

            // 计算剩余的秒数
            $secondsLeft = $todayEndTimestamp - $currentTimestamp;

            $increment = 1;
            Cache::set($cacheKey, $increment, $secondsLeft);
        }

        $clientId = str_pad($customerId, 4, 0, STR_PAD_LEFT);
        $number = str_pad($increment, 4, 0, STR_PAD_LEFT);

        return $pre . $clientId . $date . $number;
    }
}

if (!function_exists('generateStockOrderId')) {
    /**
     * @desc 备货订单-系统订单号
     * 生成规则：SO+客户ID+年月日+3位数字，例如：SO01240608001
     * @return string
     */
    function generateStockOrderId(int $customerId = 0)
    {
        $pre = 'SO';
        $customerId = $customerId ?: getCustomId();
        $date = date('ymd');
        $cacheKey = 'systemStockOrderNumber:' . $customerId . ':' . $date;

        if (Cache::has($cacheKey)) {
            $increment = Cache::increment($cacheKey);
        } else {
            // 获取当前的时间戳（秒）
            $currentTimestamp = time();

            // 获取今天的结束时间戳（秒）
            $todayEndTimestamp = strtotime("tomorrow") - 1;

            // 计算剩余的秒数
            $secondsLeft = $todayEndTimestamp - $currentTimestamp;

            $increment = 1;
            Cache::set($cacheKey, $increment, $secondsLeft);
        }

        $clientId = str_pad($customerId, 3, 0, STR_PAD_LEFT);
        $number = str_pad($increment, 3, 0, STR_PAD_LEFT);

        return $pre . $clientId . $date . $number;
    }
}

if (!function_exists('getClientDomain')) {
    function getClientDomain()
    {
        $host = Tenant::current()->client_domain;
        $http = config('app.env') === 'production' || config('app.env') === 'development' ? 'https://' : 'http://';
        return $http . $host;
    }
}

if (!function_exists('getCurrentUuid')) {
    function getCurrentUuid()
    {
        return Tenant::current()->uuid;
    }
}

if (!function_exists('remove_special_char')) {
    /**
     *
     * @param string $str
     * @return string
     */
    function remove_special_char(string $str)
    {
        return str_replace(['/', '\\', ':', '*', '"', '<', '>', '|', '?', ' ', '#', '%'], '_', $str);
    }
}

if (!function_exists('isCanadaPostCode')) {
    /**
     * 是否为加拿大邮编
     * @param string $code
     * @return false|int
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/16 17:42
     */
    function isCanadaPostCode(string $code)
    {
        if (empty($code)) return false;

        $reg = '/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ][ ]?\d[ABCEGHJKLMNPRSTVWXYZ]\d$/i';
        $code = strtoupper(str_replace(' ', '', $code));

        return preg_match($reg, $code);
    }
}

if (!function_exists('convertConstant')) {
    function convertConstant($data)
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = [
                'label' => $value,
                'value' => $key
            ];
        }
        return $result;
    }
}

if (!function_exists('differenceComparing')) {
    /**
     * 校验差异
     * @param array $newData
     * @param array $oldData
     * @param array $title
     * @param bool $fillOnlyData //仅填充数据： 只修改空值的字段
     * @return array
     */
    function differenceComparing(array $newData, array $oldData, array $title, bool $fillOnlyData = false): array
    {
        $data = [];
        $content = '';
        foreach ($newData as $key => $value) {
            if (!isset($title[$key])) {
                continue;
            }

            $name = $title[$key];
            $oldValue = $oldData[$key];

            if ($fillOnlyData && !empty($oldValue)) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            if (is_array($oldValue)) {
                $oldValue = implode(',', $oldValue);
            }

            if (trim($value) !== trim($oldValue)) {
                $data[$key] = $value;

                $content .= $name . ': ' . $oldValue . ' => ' . $value . '，';
            }
        }

        return ['data' => $data, 'content' => $content];
    }
}


if (!function_exists('extract_image_links')) {
    /**
     * 提取图片
     * @param string $html
     * @param int $max
     * @param string $returnType
     * @return string
     */
    function extract_image_links($html, $max = 8, $returnType = 'string')
    {

        // 使用正则表达式匹配 <img> 标签中的 src 属性
        preg_match_all('/<img[^>]+src="([^">]+)"/i', $html, $matches);

        // 获取所有匹配的链接
        $links = $matches[1];

        // 只保留最多 $max 个链接
        $links = array_slice($links, 0, $max);

        // 用英文逗号连接链接
        if ($returnType == 'string') {

            return implode(',', $links);
        }

        return $links;
    }
}

if (!function_exists('mask_string')) {
    /**
     * @param string $str
     * @return string
     */
    function mask_string($str, $displayQuantity = 6)
    {

        // 获取字符串的长度
        $length = strlen($str);

        // 如果字符串长度小于或等于6，直接返回原字符串
        if ($length <= $displayQuantity) {
            return $str;
        }

        // 替换前面的部分
        $masked = str_repeat('*', 3) . substr($str, -$displayQuantity);

        return $masked;
    }
}

if (!function_exists('customNumberFormat')) {
    function customNumberFormat($num, $decimals = 2): string
    {
        return number_format($num, $decimals, '.', '');
    }
}

if (!function_exists('generateEmployeeInviteCode')) {
    function generateEmployeeInviteCode($employeeId, $secretKey = '478befe48907d1f0480047a26f6d93ad'): string
    {
        $data = $employeeId . '_' . $secretKey;
        $hash = substr(md5($data), 0, 12); // 取MD5前12位
        $formatted = strtoupper(implode('-', str_split($hash, 4)));
        return 'MATE-' . $formatted;
    }
}

if (!function_exists('generateUniqueExternalId')) {
    /**
     * 生成唯一外部ID
     * @param string $name
     * @return string
     */
    function generateUniqueExternalId(string $name): string
    {
        // 毫秒时间戳(13位) + 随机数(6位)
        $microtime = round(microtime(true) * 1000);
        $random = mt_rand(100000, 999999);
        return $name . $microtime . $random;
    }
}

