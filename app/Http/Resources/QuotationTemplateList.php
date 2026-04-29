<?php
namespace App\Http\Resources;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 报价模板--列表字段
 */
class QuotationTemplateList extends JsonResource
{
    /**
     * @param $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cn_name' => $this->getTranslation('name', Language::CHINESE),
            'en_name' => $this->getTranslation('name', Language::ENGLISH),
            'prop_id' => $this->prop_id,
            'prop_name' => $this->prop_name,
            'express_line' => ExpressLineTemplateList::collection($this->expressLine),
            'sort' => $this->sort,
            'status' => $this->status,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
            'deleted_at' => (string)$this->deleted_at,
        ];
    }
}
