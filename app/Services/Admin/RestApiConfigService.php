<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\RestApiConfig;
use App\Exceptions\AccidentException;
use Illuminate\Support\Str;

class RestApiConfigService extends BaseService
{
    protected $orderBy = ['id' => 'desc'];

    public function __construct(RestApiConfig $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
       return $this->setOrderBy()->all();
    }

    /**
     * 新增key
     */
    public function addKey()
    {
        validator($this->formData, $this->rules())->validate();

        $appKey = $appSecret = null;
        if($this->formData['type'] == RestApiConfig::TYPE_1){

            $appKey = 'ck_' . bin2hex(Str::random(20));

            $appSecret = 'cs_' . bin2hex(Str::random(20));
        }

        return $this->model::create([
            'type' => $this->formData['type'],
            'description' => $this->formData['description'],
            'permission' => $this->formData['permission'],
            'admin_id' => getAdminId(),
            'key' => $appKey,
            'secret' => $appSecret,
        ]);

    }

    public function update($id)
    {
        validator($this->formData, $this->rules())->validate();

        try {
            $this->model::where('id', $id)->update([
                'description' => $this->formData['description'],
                'permission' => $this->formData['permission'],
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function destroy($id)
    {
        return $this->model::where('id', $id)->delete();
    }

    private function rules()
    {
        return [
            'type'  => 'required|in:' . RestApiConfig::TYPE_1,
            'description' => 'required|string|max:200',
            'permission' => 'required|in:1,2,3',
        ];
    }
}
