<?php
namespace App\Http\Resources;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineTemplateList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'cn_name' => $this->getTranslation('name', Language::CHINESE),
            'en_name' => $this->getTranslation('name', Language::ENGLISH),
            'name' => $this->name,
            'base_mode' => $this->base_mode,
            'enabled' => $this->enabled,
            'express_company_id' => $this->docking_type,
            'express_company_name' => $this->express_company_name,
            'channel_code' => $this->channel_code ?? '',
            'myLogisticsId' => $this->myLogisticsId,
            'myLogisticsChannelId' => $this->myLogisticsChannelId,
        ];
    }
}
