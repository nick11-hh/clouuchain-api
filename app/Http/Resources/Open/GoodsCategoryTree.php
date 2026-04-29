<?php
namespace App\Http\Resources\Open;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Lib\Language;

class GoodsCategoryTree extends JsonResource
{
    /**
     * @param $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent->name ?? '',
            'name' => $this->name_translate ?: $this->name,
            'name_cn' => $this->getTranslation('name_translate', Language::CHINESE) ?: $this->name,
            'name_en' => $this->getTranslation('name_translate', Language::ENGLISH) ?: $this->name,
            'name_ru' => $this->getTranslation('name_translate', Language::RUSSIAN) ?: $this->name,
            'image' => $this->image,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
            'sort' => $this->sort ?? '',
            'categories' => self::collection($this->categories ?? []),
            'created_at' => (string)$this->created_at,
            'is_recommend' => (string)$this->recommended_time ? 1 : 0,
        ];
    }
}
