<?php


namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Models\CTUMessage;
use App\Models\CTUUserMessage;
use App\Models\Custom;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CTUMessageService extends BaseService
{
    protected $filterRules = [
        'status' => ['=', 'status'],
        'type' => ['type', 'type']
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(CTUMessage $CTUMessage)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $CTUMessage;
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * 新增消息通知
     * @param $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store($data)
    {
        validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($data) {
            /**@var \App\Models\CTUMessage $message */
            $message = $this->query->create([
                'type' => $data['type'],
                'title' => $data['title'],
                'content' => $data['content'],
                'operator_id' => auth()->id(),
                'operator' => auth()->user()->username,
            ]);

            return $this->targetSync($message, $data);
        });
    }

    /**
     * @param CTUMessage $message
     * @param $data
     * @return bool
     */
    private function targetSync(CTUMessage $message, $data)
    {
        $message->userGroups()->detach();
        $message->users()->detach();

        if ($message->type == $this->model::TYPE_ALL) {
            $message->update([
                'total_count' => User::query()->count()
            ]);
            return true;
        };

        if ($message->type == $this->model::TYPE_USER_GROUP) {
            $message->userGroups()->sync($data['target_ids']);
            $message->update([
                'total_count' => User::query()->whereIn('group_id', $data['target_ids'])->count()
            ]);
            return true;
        }

        if ($message->type == $this->model::TYPE_USER) {
            $message->users()->sync($data['target_ids']);
            $message->update([
                'total_count' => User::query()->whereKey($data['target_ids'])->count()
            ]);
            return true;
        }

        return true;
    }

    /**
     * 更新消息通知
     * @param $id
     * @param $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function update($id, $data)
    {
        validator($data, $this->rules())->validate();

        /**@var \App\Models\CTUMessage $message */
        $message = parent::show($id);

        throw_if(
            $message->status == 1,
            new AccidentException('不能修改已发布信息')
        );
        return DB::transaction(function () use ($data, $message) {
            $message->update([
                'type' => $data['type'],
                'title' => $data['title'],
                'content' => $data['content'],
                'operator_id' => auth()->id(),
                'operator' => auth()->user()->username,
            ]);

            return $this->targetSync($message, $data);
        });
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateTrans(int $id, array $data)
    {
        validator($data, $this->translateRules())->validate();

        /** @var CTUMessage $message */
        $message = $this->model::query()->findOrFail($id);

        throw_if(
            $message->status == 1,
            new AccidentException('不能修改已发布信息')
        );

        if (!empty($data['title'])) {
            $message->setTranslations('title', [$data['language'] => $data['title']]);
        }

        if (!empty($data['content'])) {
            $message->setTranslations('content', [$data['language'] => $data['content']]);
        }

        return $message->save();
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Throwable
     */
    public function destroy($id)
    {
        /**@var \App\Models\CTUMessage $message */
        $message = parent::show($id);

        throw_if(
            $message->status == 1,
            new AccidentException('不能删除已发布信息')
        );
        return DB::transaction(function () use ($message) {
            $message->userGroups()->detach();
            $message->users()->detach();

            return $message->delete();
        });
    }

    public function push($id)
    {
        $message = parent::show($id);

        throw_if(
            $message->status == 1,
            new AccidentException('不能发布已发布的信息')
        );
        return DB::transaction(function () use ($message) {
            $this->beginPush($message);

            return $message->update([
                'status' => $this->model::STATUS_FINISH
            ]);
        });
    }

    public function rules()
    {
        return [
            'type' => 'required|int|in:1,2,3',
            'title' => 'required|string|max:150',
            'content' => 'required|string',
            'target_ids' => 'required_if:type,2,3|array'
        ];
    }

    protected function translateRules()
    {
        return array_merge(
            parent::translateRules(),
            [
                'title' => 'nullable|string',
                'content' => 'nullable|string',
            ]
        );
    }

    public function beginPush($message)
    {
        return DB::transaction(function () use ($message) {
            match ($message->type) {
                CTUMessage::TYPE_ALL => $this->pushToAll($message),
                CTUMessage::TYPE_USER_GROUP => $this->pushToUserGroup($message),
                CTUMessage::TYPE_USER => $this->pushToUser($message),
                default => true
            };
        });
    }

    private function pushToAll(CTUMessage $message)
    {
        Custom::query()
            ->chunkById(500, function ($users) use ($message) {
                $this->pushMessage($message, $users);
            });

        return true;
    }

    private function pushToUserGroup(CTUMessage $message)
    {
        Custom::query()
            ->whereIn('group_id', $message->userGroups()->get()->modelKeys())
            ->chunkById(500, function ($users) use ($message) {
                $this->pushMessage($message, $users);
            });

        return true;
    }

    private function pushToUser(CTUMessage $message)
    {
        Custom::query()
            ->whereKey($message->users()->get()->modelKeys())
            ->chunkById(500, function ($users) use ($message) {
                $this->pushMessage($message, $users);
            });

        return true;
    }

    private function pushMessage(CTUMessage $message, Collection $users)
    {
        $newUsers = [];
        $users->each(function ($user) use ($message, &$newUsers) {
            $newUsers[] = [
                'user_id' => $user->id,
                'message_id' => $message->id,
                'created_at' => now(),
                'updated_at' => now()
            ];
        });

        if (empty($newUsers)) return;

        CTUUserMessage::query()->insert($newUsers);
    }
}
