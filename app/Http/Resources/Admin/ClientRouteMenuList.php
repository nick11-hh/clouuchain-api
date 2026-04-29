<?php

namespace App\Http\Resources\Admin;

use App\Lib\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientRouteMenuList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'name_cn'  => $this->getTranslation('name_translate', Language::CHINESE),
            'name_en'  => $this->getTranslation('name_translate', Language::ENGLISH),
            'name_ru'  => $this->getTranslation('name_translate', Language::RUSSIAN),
            'name_ar'  => $this->getTranslation('name_translate', Language::ARABIC),
            'name_pt'  => $this->getTranslation('name_translate', Language::PORTUGAL),
            'name_vi'  => $this->getTranslation('name_translate', Language::VIETNAM),
            'tag'      => $this->tag,
            'children' => self::collection($this->allRoutes ?? $this->routes),
            'enabled'  => $this->enabled,
            'is_show'  => $this->is_show,
            'level'    => $this->level,
        ];
    }
}
