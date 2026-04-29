<?php

namespace App\Http\Resources\Admin;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsCategoryTree extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
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
            'name_ar' => $this->getTranslation('name_translate', Language::ARABIC) ?: $this->name,
            'name_pt' => $this->getTranslation('name_translate', Language::PORTUGAL) ?: $this->name,
            'name_vi' => $this->getTranslation('name_translate', Language::VIETNAM) ?: $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
            'sort' => $this->sort ?? '',
            // 'categories' => $this->categories ?? [],
            'categories' => self::collection($this->categories ?? []),
            'created_at' => (string)$this->created_at,
            'is_recommend' => (string)$this->recommended_time ? 1 : 0,
        ];
    }
}
