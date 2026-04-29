<?php

/**
 * @Author: h9471
 * @Created: 2022/11/21 15:40
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\UserAddressTag;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class UserAddressTagService extends BaseService
{
    protected $filterRules = [];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(UserAddressTag $userAddress)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $userAddress;
        $this->query = $userAddress->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return Builder[]|Collection
     */
    public function index(): Collection|array
    {
        $this->query->withCount('addresses');

        return parent::all();
    }

    /**
     * @param array $data
     * @return Builder|Model
     * @throws Throwable
     */
    public function create(array $data): Model|Builder
    {
        $data = validator($data, $this->createRules())->validate();

        return UserAddressTag::query()->create([
            'name' => $data['name'],
            'remark' => $data['remark'] ?? '',
        ]);
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(int $id, array $data): bool
    {
        $data = validator($data, $this->createRules())->validate();

        $tag = UserAddressTag::query()->findOrFail($id);

        return $tag->update([
            'name' => $data['name'],
            'remark' => $data['remark'] ?? '',
        ]);
    }

    /**
     * @param int $id
     * @return bool|mixed|null
     * @throws Exception
     */
    public function destroy(int $id): mixed
    {
        $addressTag = UserAddressTag::query()
            ->withCount('addresses', 'conditions')
            ->findOrFail($id);

        if ($addressTag->addresses_count > 0) {
            throw new AccidentException('该标签已被地址使用，无法删除', Code::OPERATE_FAIL);
        }

        if ($addressTag->conditions_count > 0) {
            throw new AccidentException('该标签已被渠道使用，无法删除', Code::OPERATE_FAIL);
        }

        return $addressTag->delete();
    }

    /**
     * @return string[]
     */
    protected function createRules()
    {
        return [
            'name' => 'required|string|max:20',
            'remark' => 'sometimes|nullable|string|max:255',
        ];
    }
}
