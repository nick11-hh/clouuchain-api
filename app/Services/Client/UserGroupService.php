<?php

namespace App\Services\Client;

use App\Lib\Code;
use App\Models\AdminGroupModel;
use App\Models\ClientMenu;
use App\Models\Permission;
use App\Models\RouteMenuModel;
use App\Models\UserGroup;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class UserGroupService extends BaseService
{
    public function __construct()
    {
        $this->model = new UserGroup();
        $this->formData = request()->all();
    }

    public function index()
    {
        $pageSize = $this->formData['page_size'] ?? 10;
        $query = UserGroup::query()->withCount('users')->where('custom_id', getCustomId());
        return $query->latest()->paginate($pageSize);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return $this->model::where('custom_id', getCustomId())->findOrFail($id);
    }

    /**
     * @param $params
     * @return void
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $params['custom_id'] = getCustomId();
        $userGroupData = UserGroup::init($params);
        return UserGroup::create($userGroupData);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        $userGroup = $this->model::where('custom_id', getCustomId())->findOrFail($id);
        if (isset($params['group_name'])) $userGroup->group_name = $params['group_name'];
        if (isset($params['description'])) $userGroup->description = $params['description'];
        if (isset($params['menu_limit'])) $userGroup->menu_limit = $params['menu_limit'];
        return $userGroup->save();
    }


    /** 删除用户组
     * @param $params
     * @return mixed
     * @throws Exception
     */
    public function deletes($params)
    {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的用户', Code::OPERATE_FAIL);
        return $this->model::whereIn('id', $params['ids'])
            ->where('custom_id', getCustomId())
            ->where('is_default', 0)->delete();
    }

    protected function rules()
    {
        return [
            'group_name' => 'required|string|between:1,30',
            'description' => 'sometimes|string|nullable'
        ];
    }

    /**
     * 获取员工组权限
     * @param int $id
     * @return Builder[]|Collection
     * @throws Exception
     */
    public function getPermissions(int $id): Collection|array
    {
        if ($this->model::first()->id === $id) {
            //throw new AccidentException('系统初始用户组权限不能修改', Code::OPERATE_FAIL);
            //产品需求，不要弹出错误窗口
            return [];
        }

        $routeIds = $this->model::with('routeMenus')
            ->where('id', $id)->get()
            ->pluck('routeMenus')->flatten()
            ->pluck('id')->toArray();

        $menus = ClientMenu::query()->with(['routes'])
            // ->where('custom_id', getCustomId())
            ->where('parent_id', 0)
            ->where('is_show', 1)->get();
        $menus->flatMap(function ($level1) use ($routeIds) {
            if (in_array($level1->id, $routeIds)) {
                $level1->enabled = 1;
            } else {
                $level1->enabled = 0;
            }
            $level1->routes->flatMap(function ($level2) use ($routeIds) {
                if (in_array($level2->id, $routeIds)) {
                    $level2->enabled = 1;
                } else {
                    $level2->enabled = 0;
                }
                $level2->routes=[];
                /*$level2->routes->flatMap(function ($level3) use ($routeIds) {
                    if (in_array($level3->id, $routeIds)) {
                        $level3->enabled = 1;
                    } else {
                        $level3->enabled = 0;
                    }
                    $level3->routes->flatMap(function ($level4) use ($routeIds) {
                        if (in_array($level4->id, $routeIds)) {
                            $level4->enabled = 1;
                        } else {
                            $level4->enabled = 0;
                        }
                    });
                    $level3->enabled = $level3->enabled ?: ($level3->routes->sum('enabled') > 0 ? 1 : 0);
                });
                $level2->enabled =  $level2->enabled ?: ($level2->routes->sum('enabled') > 0 ? 1 : 0);*/

            });
            $level1->enabled = $level1->enabled ?: ($level1->routes->sum('enabled') > 0 ? 1 : 0);
        });

        return $menus;
    }

    /**
     * 更新员工组权限
     * @param $id
     * @param $data
     * @return bool
     * @throws \Throwable
     */
    public function updatePermissions($id, $data): bool
    {
        validator($data, $this->permissionRules())->validate();
        $data = $data['permissions'];
        $userGroup = $this->model::findOrFail($id);
//        throw_if(!ClientMenu::isValid($data), new AccidentException('权限不存在！', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($userGroup, $data) {
            return count($userGroup->routeMenus()->sync($data)) > 0;
        });
    }

    public function getDefaultGroupPermissions()
    {
        // return ClientMenu::query()->where('custom_id', getCustomId())->where('parent_id', 0)->where('is_show', 1)->with('routes')->get();
        return ClientMenu::query()->where('parent_id', 0)->where('is_show', 1)->with('routes')->get();
    }


    private function permissionRules(): array
    {
        return [
            'permissions' => 'required|array',
            'permissions.*' => 'required|integer',
        ];
    }

}
