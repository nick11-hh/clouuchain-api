<?php

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\CreditCardTypes;

class CreditCardService extends BaseService
{
    public function __construct(public CreditCardTypes $creditCardType)
    {

    }

    /**
     * @return array|\Illuminate\Database\Eloquent\Builder[]
     */
    public function all(): array
    {
        return CreditCardTypes::get()->toArray();
    }


    /**
     * @param int $id
     * @param int $status
     * @return CreditCardTypes|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object
     * @throws AccidentException
     */
    public function status(int $id, int $status)
    {
        $creditCardType = CreditCardTypes::where('id', $id)->first();
        if (empty($creditCardType)) {
            throw new AccidentException('信用卡信息不存在', Code::OPERATE_FAIL);
        }
        if (empty($creditCardType['client_id']) && empty($creditCardType['client_secret']) && empty($creditCardType['webhook_secret'])) {
            throw new AccidentException('请先配置所选信用卡支付信息再设置状态', Code::OPERATE_FAIL);
        }
        $creditCardType->status = $status;
        $creditCardType->updated_at = date('Y-m-d H:i:s');
        $creditCardType->save();
        return $creditCardType;
    }

    public function update(array $data)
    {
        $creditCardType = CreditCardTypes::where('id', $data['id'])->first();
        if (empty($creditCardType)) {
            throw new AccidentException('信用卡信息不存在', Code::OPERATE_FAIL);
        }
        $creditCardType->client_id = $data['client_id'];
        $creditCardType->client_secret = $data['client_secret'];
        $creditCardType->webhook_secret = $data['webhook_secret'];
        $creditCardType->minimum_payment = $data['minimum_payment'];
        $creditCardType->service_charge_rate = $data['service_charge_rate'];
        $creditCardType->service_charge_amount = $data['service_charge_amount'];
        $creditCardType->updated_at = date('Y-m-d H:i:s');
        $creditCardType->save();
        return $creditCardType;
    }
}
