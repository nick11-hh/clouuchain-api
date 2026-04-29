<?php

/**
 * @Author: h9471
 */

namespace App\Services\Admin;

use App\Exceptions\AccidentException;
use App\Models\Country;
use App\Models\RemoteDestination;
use App\Models\RemoteType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 偏远地区
 *
 * Class AnnouncementService
 * @package App\Services\Admin
 */
class RemoteDestinationService extends BaseService
{
    public $filterRules = [
        'remote_type_id' => ['=', 'remote_type_id'],
        'operator_name' => ['like', 'keyword'],
        'source' => ['=', 'source']
    ];

    public function __construct(RemoteDestination $remoteDestination)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $remoteDestination;
        $this->query = $remoteDestination->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $keyword = $this->formData['keyword'] ?? '';
        //默认搜索
        $this->query->when($keyword, function ($query) use ($keyword) {
            $query->where('operator_name', 'like', "%$keyword%")
                ->orWhere('city', '=', strtolower($keyword))
                ->orWhereHas('country', function ($query) use ($keyword) {
                    $query->where('code', strtolower($keyword));
                });
        });
        return parent::index();
    }

    /**
     * @throws AccidentException
     */
    public function clientIndex()
    {
        $postcode = $this->formData['postcode'] ?? '';

        if (empty($postcode)) {
            throw new AccidentException('邮编不能为空');
        }

        //默认搜索
        $this->query->where(function ($query) use ($postcode) {
            $query->whereRaw("start_postcode <= ? AND end_postcode >= ?", [$postcode, $postcode]);
        });

        $this->setFilter()->setOrderBy();

        return parent::all();
    }

    public function store($data)
    {
        $payload = validator($data, $this->rules())->validate();

        RemoteType::query()->findOrFail($data['remote_type_id']);
        $country = Country::query()->findOrFail($data['country_id']);

        $payload['country_code'] = strtolower($country->code);;
        $payload['operator_id'] = auth()->id();
        $payload['operator_name'] = auth()->user()->name ?? '';

        return $this->model::query()->create($payload);
    }

    public function batchStore($data)
    {
        $payload = validator($data, $this->batchRules())->validate();
        $type = RemoteType::query()->findOrFail($payload['remote_type_id']);

        $codes = array_unique(array_column($payload['list'], 'country_code'));
        $countries = Country::query()->whereIn('code', $codes)->get();
        throw_if(
            count($codes) !== $countries->count(),
            new AccidentException('部分国家不存在')
        );

        $base = [
            'remote_type_id' => $type->id,
            'operator_id' => auth()->id(),
            'operator_name' => auth()->user()->name ?? '',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($payload, $type, $base, $countries) {
            collect($payload['list'])->map(function ($rd) use ($type, $base, $countries) {
                $base['country_id'] = $countries->firstWhere('code', strtolower($rd['country_code']))->id ?? 0;

//                unset($rd['country_code']);

                return array_merge($rd, $base);
            })->chunk(200)->each(function ($chunks) {
                $this->model::query()->insert($chunks->toArray());
            });
        });

        return true;
    }

    public function update($id, $data)
    {
        $payload = validator($data, $this->rules())->validate();

        $destination = $this->model::query()->findOrFail($id);

        throw_if(
            $destination->source == RemoteType::SOURCE_SYSTEM,
            new AccidentException('当前偏远地区为系统配置，不能修改')
        );
        $country = Country::query()->findOrFail($data['country_id']);

        $payload['country_code'] = strtolower($country->code);
        $payload['operator_id'] = auth()->id();
        $payload['operator_name'] = auth()->user()->name ?? '';

        return $destination->update($payload);
    }

    public function destroy($data)
    {
        validator($data, $this->destroyRules())->validate();

        throw_if(
            $this->model::query()->whereKey($data['ids'])
                ->where('source', RemoteType::SOURCE_SYSTEM)
                ->first(),
            new AccidentException('偏远类型存在部分来源是系统内置，不可删除，请重新操作')
        );

        return parent::delete($data['ids']);
    }


    private function rules()
    {
        return [
            'remote_type_id' => 'required|int',
            'country_id' => 'required|int',
            'city' => 'sometimes|nullable|string',
            'start_postcode' => 'required_without:city|nullable|string',
            'end_postcode' => 'required_without:city|nullable|string',
            'grade' => 'sometimes|nullable|string|in:A,B,C'
        ];
    }

    private function batchRules()
    {
        return [
            'remote_type_id' => 'required|int',
            'list' => 'required|array',
            'list.*.country_code' => 'required|string',
            'list.*.city' => 'sometimes|nullable|string',
            'list.*.start_postcode' => 'required_without:list.*.city|nullable|string',
            'list.*.end_postcode' => 'required_without:list.*.city|nullable|string',
            'list.*.grade' => 'sometimes|nullable|string|in:A,B,C'
        ];
    }

    private function destroyRules()
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|int'
        ];
    }

}
