<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\ExchangeRateLogsModel;
use App\Models\ExchangeRateModel;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Exceptions\AccidentException;

class ExchangeRateService extends BaseService
{
    public function __construct(ExchangeRateModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function store()
    {
        validator($this->formData, $this->rules(), [], [
            'name'                 => '货币名称',
            'currency_code'        => '货币代码',
            'symbol'               => '代币符号',
            'exchange_rate'        => '官方汇率',
            'custom_exchange_rate' => '自定义汇率',
        ])->validate();

        throw_if(
            $this->model::where('currency_code', $this->formData['currency_code'])->first(),
            new AccidentException('操作失败，货币已存在', Code::OPERATE_FAIL)
        );

        $data = $this->model::init($this->formData);

        DB::beginTransaction();
        try {
            $exchangeRate = $this->model::create($data);

            $log = [
                'exchange_rate_id' => $exchangeRate->id,
                'user_id' => auth()->id(),
                'type' => ExchangeRateLogsModel::TYPE_CREATE,
                'new_exchange_rate' => $this->formData['custom_exchange_rate']
            ];

            ExchangeRateLogsModel::create($log);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('添加汇率失败：'.$e->getMessage());
            throw new AccidentException('添加汇率失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function update($id)
    {
        validator($this->formData, [
            'custom_exchange_rate' => 'required'
        ], [], [
            'custom_exchange_rate' => '自定义汇率'
        ])->validate();

        $oldExchangeRate = $this->model::where('id', $id)->value('custom_exchange_rate');

        $log = [
            'exchange_rate_id' => $id,
            'user_id' => auth()->id(),
            'type' => ExchangeRateLogsModel::TYPE_EDIT,
            'old_exchange_rate' => $oldExchangeRate,
            'new_exchange_rate' => $this->formData['custom_exchange_rate']
        ];

        DB::beginTransaction();
        try {

            // 写操作日志
            ExchangeRateLogsModel::create($log);

            // 更新汇率
            $this->model::where('id', $id)->update(['custom_exchange_rate' => $this->formData['custom_exchange_rate']]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            logger('更新汇率失败: '.$e->getMessage());
            throw new AccidentException('更新汇率失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 同步官方汇率
     * @return bool
     * @throws Exception
     */
    public function syncExchangeRate()
    {
        $res = $this->model::select('id', 'currency_code')->get();

        $res->each(function ($item) {
            $response = $this->queryExchangeRate($item['currency_code'], 'USD');

            if($response) {
                $this->model::where('id', $item['id'])->update(['exchange_rate' => $response['rate']]);
            }
        });

        return true;
    }

    /**
     * 获取汇率
     * @param string $to
     * @param string $from
     * @return array|bool
     * @throws Exception
     */
    public function queryExchangeRate(string $to, string $from = 'USD'): array|bool
    {
        if ($from === $to) {
            return ['rate' => 1];
        }

        $params = [
            'key' => '60a0696d9d521844a91ef31abbafb613',
            'from' => $from,
            'to' => $to,
        ];
        $response = Http::get('http://op.juhe.cn/onebox/exchange/currency', $params);

        if ($response->failed()) {
            logger('查询官方汇率失败', ['params' => $params]);
            throw new AccidentException('汇率更新失败，请稍后重试', Code::OPERATE_FAIL);
        }

        if ($response->successful()) {
            $data = $response->json();

            if ($data['error_code'] === 0) {
                return ['rate' => number_format($data['result'][0]['exchange'], 4)];
            } else {
                return $this->fallbackExchangeRate($to, $from);
            }
        }

        return false;
    }

    /**
     * @param  string  $to
     * @param  string  $from
     * @return array|false
     * @throws Exception
     */
    public function fallbackExchangeRate(string $to, string $from = 'USD'): bool|array
    {
        $response = Http::get('https://api.freecurrencyapi.com/v1/latest', [
            'apikey' => 'fca_live_ebi1kpHPfxvTKkI3abDQdemQQPZajzhbR9pgQHxb',
            'base_currency' => $from,
            'currencies' => $to,
        ]);

        if ($response->failed()) {
            throw new AccidentException('汇率更新失败，请稍后重试', Code::OPERATE_FAIL);
        }

        if ($response->successful()) {
            $data = $response->json();

            info('fallbackExchangeRate', $data);

            if (empty($data['data'][$to])) {
                throw new AccidentException('汇率更新失败，请稍后重试', Code::OPERATE_FAIL);
            }

            return ['rate' => number_format($data['data'][$to], 4)];
        }

        return false;
    }

    /**
     * 获取支持的货币列表
     * @return array[]
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/26 15:53
     */
    public function getSupportCurrency()
    {
        return $this->model->getSupportCurrency();
    }

    /**
     * 获取所有货币汇率
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/8 15:14
     */
    public function getRates()
    {
        $result = $this->query->select(['custom_exchange_rate as rate', 'currency_code'])->orderByDesc('id')->get()->keyBy('currency_code')->toArray();
        $arr = [];
        foreach ($result as $key => $item) {
            $arr[$key] = $item['rate'];
        }

        return $arr;
    }

    public function rules(): array
    {
        return [
            'name'                 => 'required',
            'currency_code'        => 'required',
            'symbol'               => 'required',
            'exchange_rate'        => 'required',
            'custom_exchange_rate' => 'required',
        ];
    }
}
