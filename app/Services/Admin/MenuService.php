<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Lib\Language;
use App\Models\ClientMenu;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;

class MenuService extends BaseService
{
    public $filterRules = [];

    public function __construct()
    {
        $this->model = new ClientMenu();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function getClientMenuTree()
    {
        // $customerId = $this->query->latest()->value('custom_id');

        $query = $this->model::query();

        //调整权限后可去掉这块的查询条件
        // $query->where('custom_id', $customerId);

        $query->where('parent_id', 0)->with(['allRoutes']);

        $menus = $query->get();

        //只查询到二级菜单
        $menus->flatMap(function ($level1) {
            $level1->allRoutes->flatMap(function ($level2) {
                $level2->allRoutes = [];
            });
        });

        return $menus;
    }

    public function updateClientMenuTree()
    {
        validator($this->formData, [
            'menus.*.tag' => 'required',
            'menus.*.name' => 'required',
            'menus.*.field' => 'required',
            'menus.*.value' => 'required',
        ])->validate();

        return DB::transaction(function () {
            $menus = $this->formData['menus'];
            foreach ($menus as $menu) {
                $tag = $menu['tag'];
                $field = $menu['field'];
                $value = $menu['value'];
                $language = '';
                switch ($field) {
                    case 'name_cn':
                        $language = Language::CHINESE;
                        break;
                    case 'name_en':
                        $language = Language::ENGLISH;
                        break;
                    case 'name_ru':
                        $language = Language::RUSSIAN;
                        break;
                    case 'name_ar':
                        $language = Language::ARABIC;
                        break;
                    case 'name_pt':
                        $language = Language::PORTUGAL;
                        break;
                    case 'name_vi':
                        $language = Language::VIETNAM;
                        break;
                    default:
                        $this->model::query()->where('tag', $tag)->update([$field => $value]);
                        break;
                }
                if ($language) {
                    $menus = $this->model::query()->where('tag', $tag)->get();
                    $menus->each(function ($item) use ($language, $value) {
                        $item->setTranslation('name_translate', $language, $value);
                        $item->save();
                    });
                }

            }

            return true;
        });
    }

    protected function rule()
    {
        return [
        ];
    }

}
