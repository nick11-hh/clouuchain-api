<?php

namespace App\Services\Client;


use App\Lib\Code;
use App\Models\Custom;
use App\Models\User;
use App\Exceptions\AccidentException;


class UserService extends BaseService
{
    public function __construct()
    {
        $this->model = new User();
        $this->formData = request()->all();
    }

    public function index()
    {
        $search = $this->formData['search'] ?? '';
        $status = $this->formData['status'] ?? '';
        $group = $this->formData['group'] ?? '';
        $pageSize = $this->formData['page_size'] ?? 10;
        $query = $this->model::with('custom', 'userGroup')->where('custom_id',  getCustomId());
        $query->when($search, function ($query) use ($search) {
           return $query->where(function ($query) use ($search) {
               return $query->where('username', $search)->orWhere('phone', $search)->orWhere('email', $search);
           });
        });
        $query->when($status, function ($query) use ($status) {
            return $query->where('status', $status);
        });
        $query->when($group, function ($query) use ($group) {
            return $query->where('group_id', $group);
        });
        return $query->latest()->paginate($pageSize);
    }

    public function show($id)
    {

    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $params['custom_id'] = getCustomId();
        $user = User::where('username', $params['username'])
            ->orWhere('email', $params['email'])
            ->orWhere('phone', $params['phone'])->first();
        if (!empty($user)) {
            if ($user->username === $params['username']) throw new AccidentException('用户名已存在', Code::OPERATE_FAIL);
            if ($user->email === $params['email']) throw new AccidentException('该邮箱已注册', Code::OPERATE_FAIL);
            if ($user->username === $params['phone']) throw new AccidentException('该手机号码已注册', Code::OPERATE_FAIL);
        }
        $userData = User::init($params);
        return User::create($userData);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->updateRule())->validate();
        $user = User::where('custom_id', getCustomId())->findOrFail($id);
        $user->username = $params['username'];
        $user->email = $params['email'];
        $user->phone = $params['phone'];
        $user->phone_area_code = $params['phone_area_code'] ?? '';
        $user->group_id = $params['group_id'];
        $user->name = $params['name'];
        return $user->save();
    }

    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        return User::whereIn('id', $params['ids'])
            ->where('custom_id', getCustomId())
            ->update(['status' => $params['status']]);
    }

    public function deletes($params) {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的用户', Code::OPERATE_FAIL);
        return User::whereIn('id', $params['ids'])
            ->where('custom_id', getCustomId())
            ->where('is_main', 0)->delete();
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function changePassword($id, $params)
    {
        $user = User::where('custom_id', getCustomId())->findOrFail($id);
        $user->password = bcrypt($params['password']);
        return $user->save();
    }

    public function rules()
    {
        return [
            'username' => 'required|string|between:2,30',
            'password' => 'required|string|between:6,20',
            'email' => 'required|email',
            'phone' => 'required|string',
            'group_id' => 'required|int',
            'name' => 'sometimes|string',
            'phone_area_code' => 'sometimes|nullable|string',
        ];
    }

    public function updateRule()
    {
        return [
            'username' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'group_id' => 'required|int',
            'name' => 'sometimes|nullable|string',
            'phone_area_code' => 'sometimes|nullable|string',
        ];
    }
}
