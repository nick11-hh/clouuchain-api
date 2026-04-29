<?php
namespace App\Services\Admin;

use App\Lib\Code;
use App\Lib\Language;
use App\Models\ExpressLineModel;
use App\Models\PackageProp;
use App\Models\QuotationTemplateModel;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * 报价模板--服务类
 */
class QuotationTemplateService extends BaseService
{
    protected $orderBy = ['status' => 'desc', 'sort' => 'asc', 'id' => 'asc'];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new QuotationTemplateModel();
        $this->query = $this->model->newQuery();
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->query->with([
            'expressLine',// 关联运费模板
        ]);

        $this->search();

        return parent::index();
    }

    /**
     * 获取所有列表数据
     */
    public function list()
    {
        $this->query->with([
            'expressLine',
        ])->where('status', 1);

        if (isset($this->formData['goods_once_price'])) {
            $this->query->where('is_fixed_price', $this->formData['goods_once_price']);
        } else {
            $this->query->where('is_fixed_price', 0);
        }
        if ($this->formData['prop_id'] ?? '') {
            $prop_id = $this->formData['prop_id'];
            if ($this->formData['goods_once_price'] == 1) {
                $this->query->where('prop_id', 1);
            } else {
                $this->query->where('prop_id', $prop_id);
            }
        }

        return parent::all();
    }

    /**
     * @return void
     */
    protected function search(): void
    {
        if ($this->formData['keyword'] ?? '') {
            $keyword = $this->formData['keyword'];

            $this->query->where(function ($query) use ($keyword) {
                $query->where('cn_name', 'like', "%{$keyword}%")
                    ->orWhere('en_name', 'like', "%{$keyword}%");
            });
        }
    }

    /**
     * 创建
     * @param array $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function createTemplate(array $data)
    {
        validator($data, $this->rules())->validate();
        throw_unless(ExpressLineModel::isValid($data['express_line_ids']), new Exception('', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($data) {
            /** @var QuotationTemplateModel $quote */
            $newData = [
                'name' => $data['name'],
                'cn_name' => $data['name'],
                'en_name' => $data['en_name'],
                'prop_id' => $data['prop_id'],
                'prop_name' => $data['prop_name'],
                'status' => $data['status'] ?? 1,
            ];

            $quote = QuotationTemplateModel::query()->create($newData);

            $quote->setTranslation('name', Language::CHINESE, $data['name']);
            $quote->setTranslation('en_name', Language::ENGLISH, $data['en_name']);

            $quote->save();

            $quote->expressLine()->sync(collect($data['express_line_ids'])->unique());

            return $quote;
        });
    }

    /**
     * 更新
     * @param $id
     * @param array $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTemplate($id, array $data)
    {
        validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($id, $data) {
            /** @var QuotationTemplateModel $quote */
            $quote = QuotationTemplateModel::query()->findOrFail($id);

            $updateData = [
                'name' => $data['name'],
                'cn_name' => $data['name'],
                'en_name' => $data['en_name'],
                'prop_id' => $data['prop_id'],
                'prop_name' => $data['prop_name'],
                'status' => $data['status'] ?? 1,
            ];

            $quote->update($updateData);

            $quote->expressLine()->sync(collect($data['express_line_ids'])->unique());

            return $quote;
        });
    }

    /**
     * 设置状态
     * @param int $id
     * @param bool $status
     * @return bool
     */
    public function setStatus(int $id, bool $status): bool
    {
        $setting = $this->model::query()->findOrFail($id);

        return $setting->update(['status' => (int) $status]);
    }

    /**
     * 删除
     * @param int $id
     * @return mixed
     */
    public function deleteTemplate(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var QuotationTemplateModel $quote */

            $quote = QuotationTemplateModel::query()->findOrFail($id);

            QuotationTemplateModel::query()->where('id', $id)->delete();

            $quote->expressLine()->detach();

            return true;
        });
    }

    /**
     * @return string[]
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'en_name' => 'required|string|max:255',
            'prop_id' => 'required|integer|gt:0',
            'prop_name' => 'required|string|max:255',
            'express_line_ids' => 'required|array',
            'status' => 'required|in:0,1',
        ];
    }
}
