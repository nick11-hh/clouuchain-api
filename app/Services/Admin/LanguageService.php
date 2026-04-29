<?php

/**
 * @Author: h9471
 * @Created: 2020/3/18 16:37
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\AdminLanguages;
use App\Models\SuperAdminLanguage;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class LanguageService extends BaseService
{
    use HasBatchDelete, HasStatusSetting;

    protected $filterRules = [
        'title' => ['like', 'keyword'],
        'enabled' => ['=', 'enabled'],
    ];

    protected $orderBy = ['id' => 'asc'];

    public function __construct(AdminLanguages $language)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $language;
        $this->query = $language->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return mixed
     */
    public function availableIndex(): mixed
    {
        $this->query->where('enabled', 1);

        return parent::index();
    }

    /**
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     * @throws Throwable
     */
    public function create(array $data)
    {
        validator($data, $this->rules())->validate();

        $this->getLanguageCodeOrFail($data);

        throw_if(
            $this->validateUnique($data['language_code']),
            new AccidentException('语言编码已存在', Code::OPERATE_FAIL)
        );

        return $this->model::query()->create($this->fillData($data)) !== false;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {
        validator($data, $this->rules())->validate();

        /** @var AdminLanguages $language */
        $language = $this->model::findOrFail($id);

        $this->getLanguageCodeOrFail($data);

        return $language->update($this->fillData($data)) !== false;
    }

    public function setDefault(int $id)
    {
        /** @var AdminLanguages $language */
        $language = $this->model::findOrFail($id);

        return DB::transaction(function () use ($language) {
            $this->model::where('is_default', 1)
                ->update(['is_default' => 0]);

            //将系统默认的切换成用户默认的

            return $language->update(
                [
                    'is_default' => 1,
                ]
            ) !== false;
        });
    }

    /**
     * @param  array  $data
     * @throws ModelNotFoundException
     */
    protected function getLanguageCodeOrFail(array &$data)
    {
        $data['name'] = SuperAdminLanguage::query()
            ->where('language_code', $data['language_code'])
            ->firstOrFail()->name;
    }

    /**
     * @param $languageCode
     * @return bool
     */
    protected function validateUnique($languageCode): bool
    {
        if (!AdminLanguages::where('language_code', $languageCode)->count()) {
            return false;
        }
        return true;
    }

    /**
     * @param  array  $data
     * @return array
     */
    protected function fillData(array $data): array
    {
        return [
            'name' => $data['name'],
            'language_code' => $data['language_code'],
            'enabled' => $data['enabled'] ?? 0,
            'is_default' => $data['is_default'] ?? 0,
        ];
    }

    /**
     * @return array
     */
    private function rules(): array
    {
        return [
            'language_code' => 'required|string',
            'enabled' => 'sometimes|nullable|integer|min:0|max:1',
            'is_default' => 'sometimes|nullable|integer|min:0|max:1',
        ];
    }
}
