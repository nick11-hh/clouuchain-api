<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Admin;

use App\Http\Resources\CommonOriginNameList;
use App\Http\Resources\CountryExpressList;
use App\Http\Resources\ExpressLinePriceList;
use App\Http\Resources\PackagePropList;
use App\Http\Resources\WarehouseAddress;
use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineSimpleList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'cn_name' => $this->getTranslation('name', Language::CHINESE),
                'en_name' => $this->getTranslation('name', Language::ENGLISH),
                'name' => $this->name,
                'ru_name' => $this->getTranslation('name', Language::RUSSIAN),
                'ar_name' => $this->getTranslation('name', Language::ARABIC),
                'pt_name' => $this->getTranslation('name', Language::PORTUGAL),
                'vi_name' => $this->getTranslation('name', Language::VIETNAM),
                'enabled' => $this->enabled,
                'code' => $this->code,
            ];
    }
}
