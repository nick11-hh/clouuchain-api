<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Lib\Language;
use App\Models\ChargeTypesModel;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ChargeTypesService extends BaseService
{
    public $filterRules = [
        'status' => ['=', 'status'],
        'type' => ['=', 'type'],
    ];

    public function __construct()
    {
        $this->model = new ChargeTypesModel();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $language = $this->formData['language'] ?? Language::CHINESE;

        app()->setLocale($language);

        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @return mixed
     */
    public function store()
    {
        validator($this->formData, $this->rules())->validate();

        $data = $this->model::init($this->formData);
        return $this->model::query()->create($data);
    }

    public function update($id)
    {
        validator($this->formData, $this->rules())->validate();
        $params = $this->formData;
        $chargeType = $this->model::query()->findOrFail($id);

        if ($chargeType->name_translate) {
            if (isset($params['name'])) {
                $chargeType->name = $params['name'];
                $chargeType->setTranslation('name_translate', Language::CHINESE, $params['name']);
            }

            if (isset($params['name_en'])) {
                $chargeType->setTranslation('name_translate', Language::ENGLISH, $params['name_en']);
            }

            if (isset($params['name_ru'])) {
                $chargeType->setTranslation('name_translate', Language::RUSSIAN, $params['name_ru']);
            }

            if (isset($params['name_ar'])) {
                $chargeType->setTranslation('name_translate', Language::ARABIC, $params['name_ar']);
            }

            if (isset($params['name_pt'])) {
                $chargeType->setTranslation('name_translate', Language::PORTUGAL, $params['name_pt']);
            }

            if (isset($params['name_vi'])) {
                $chargeType->setTranslation('name_translate', Language::VIETNAM, $params['name_vi']);
            }
        } else {
            $data = $this->model::init($params);

            $chargeType->name = $data['name'];
            $chargeType->name_translate = $data['name_translate'];
        }


        return $chargeType->save();
    }

    public function deletes($params)
    {
        if (empty($params['ids'])) throw new AccidentException('请选择需要删除的费用项', Code::OPERATE_FAIL);
        $list = $this->model::query()->with('order:id,charge_type_id')->whereIn('id', $params['ids'])->get();

        $error = [];
        $list->each(function ($item) use (&$error) {
            if ($item->order) {
                $error[] = $item->name;
            } else {
                $item->delete();
            }
        });

        if (!empty($error)) {
            throw new AccidentException('删除失败，费用项已被使用:' . implode(',', $error), Code::OPERATE_FAIL);
        }

        return true;
    }

    protected function rules()
    {
        return [
            'type' => 'required|int',
            'name' => 'required|string',
        ];
    }

}
