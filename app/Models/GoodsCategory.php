<?php

namespace App\Models;

use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsCategory extends Model
{
    use HasFactory;

    use SoftDeletes;

    use CustomHasTranslations;

    protected $table = 'dsp_goods_categories';

    protected $guarded = [];

    //用于翻译
    public $translatable = ['name_translate'];

    protected $casts = [
        'name_translate' => 'array',
    ];

    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    public function categories()
    {
        return $this->child()->with(['categories'=> function($query) {
            $query->orderBy('created_at', 'desc');
        }]);
    }

    public function child()
    {
        return $this->hasMany(GoodsCategory::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(GoodsCategory::class, 'parent_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'operator', 'id');
    }

    /** 客户状态
     * @return array
     */
    public static function statusList()
    {
        return [
            self::STATUS_DISABLE => __('禁用'),
            self::STATUS_ENABLE => __('启用'),
        ];
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }


    public static function init($params)
    {
        return [
            'name' => $params['name'],
            'description' => $params['description'] ?? '',
            'parent_id' => $params['parent_id'] ?? 0,
            'image' => $params['image'] ?? '',
            'status' => $params['status'] ?? self::STATUS_ENABLE,
            'sort' => $params['sort'] ?? 100,
            'operator' => $params['operator'] ?? auth('admin')->id(),
            'recommended_time' => ($params['is_recommend'] ?? 0) > 0 ? now() : null,
            'name_translate' => [
                'zh_CN' => $params['name_cn'] ?? $params['name'],
                'en_US' => $params['name'],
                'ru_RU' => $params['name_ru'] ?? $params['name'],
                'ar_SA' => $params['name_ar'] ?? $params['name'],
                'pt_PT' => $params['name_pt'] ?? $params['name'],
                'vi_VN' => $params['name_vi'] ?? $params['name'],
            ],
        ];
    }

    public static function getCategoryLevel($categoryId)
    {
        $category1 = self::find($categoryId);

        if($category1){
            if($category1->parent_id > 0){

                $category2 = self::find($category1->parent_id);
                if($category2){
                    if($category2->parent_id > 0){

                        $category3 = self::find($category2->parent_id);
                        if($category3){

                            return [
                                'category_level_1' => [
                                    'name' => $category3->name,
                                    'level' => 1,
                                ],
                                'category_level_2' => [
                                    'name' => $category2->name,
                                    'level' => 2,
                                ],
                                'category_level_3' => [
                                    'name' => $category1->name,
                                    'level' => 3,
                                ],
                            ];
                        }else{

                            return [
                                'category_level_1' => [
                                    'name' => $category2->name,
                                    'level' => 1,
                                ],
                                'category_level_2' => [
                                    'name' => $category1->name,
                                    'level' => 2,
                                ],
                                'category_level_3' => [],
                            ];
                        }
                    }else{

                        return [
                            'category_level_1' => [
                                'name' => $category2->name,
                                'level' => 1,
                            ],
                            'category_level_2' => [
                                'name' => $category1->name,
                                'level' => 2,
                            ],
                            'category_level_3' => [],
                        ];
                    }
                }
            }else{
                return [
                    'category_level_1' => [
                        'name' => $category1->name,
                        'level' => 1,
                    ],
                    'category_level_2' => [],
                    'category_level_3' => [],
                ];
            }
        }

        return null;
    }
}
