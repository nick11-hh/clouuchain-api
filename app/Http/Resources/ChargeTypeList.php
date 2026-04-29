<?php

namespace App\Http\Resources;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargeTypeList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'name_cn' => $this->getTranslation('name_translate', Language::CHINESE) ?: $this->name,
            'name_en' => $this->getTranslation('name_translate', Language::ENGLISH) ?: $this->name,
            'name_ru' => $this->getTranslation('name_translate', Language::RUSSIAN) ?: $this->name,
            'name_ar' => $this->getTranslation('name_translate', Language::ARABIC) ?: $this->name,
            'name_pt' => $this->getTranslation('name_translate', Language::PORTUGAL) ?: $this->name,
            'name_vi' => $this->getTranslation('name_translate', Language::VIETNAM) ?: $this->name,
            'remark' => $this->remark,
            'status' => $this->status,
        ];
    }
}
