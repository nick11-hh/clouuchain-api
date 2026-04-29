<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\AdminGroupModel;
use App\Models\Permission;
use App\Models\RouteMenuModel;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class AdminGroupService extends BaseService
{
    public function __construct(AdminGroupModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('admin');
        return parent::index();
    }

    /**
     * 新建组
     * @return bool
     */
    public function store(): bool
    {
        validator($this->formData, $this->rules())->validate();

        throw_if(
            $this->model::where('name', $this->formData['name'])->first(),
            new AccidentException('操作失败，员工组名称已存在', Code::OPERATE_FAIL)
        );

        try {
            $this->model::create([
                'name' => $this->formData['name'],
                'description' => $this->formData['description'] ?? ''
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function update($id)
    {
        validator($this->formData, $this->rules())->validate();

        throw_if(
            $this->model::where('name', $this->formData['name'])->where('id', '<>', $id)->first(),
            new AccidentException('操作失败，员工组名称已存在', Code::OPERATE_FAIL)
        );

        try {
            $this->model::where('id', $id)->update([
                'name' => $this->formData['name'],
                'description' => $this->formData['description'] ?? ''
            ]);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function deletes()
    {
        validator($this->formData, ['ids' => 'required|array'], [], ['ids' => '员工组'])->validate();

        return $this->model::whereIn('id', $this->formData['ids'])->delete();
    }

    private function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'description' => 'sometimes|string|nullable|max:200',
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

        if (AdminGroupModel::first()->id === $id) {
            //throw new AccidentException('系统初始用户组权限不能修改', Code::OPERATE_FAIL);
            //产品需求，不要弹出错误窗口
            return [];
        }

        $routeIds = AdminGroupModel::with('routeMenus')
            ->where('id', $id)->get()
            ->pluck('routeMenus')->flatten()
            ->pluck('id')->toArray();


        $menus = RouteMenuModel::query()->where('parent_id', 0)->with(['routes'])->get();

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
                $level2->routes = [];
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
     * @return bool
     */
    public function updatePermissions($id, $data): bool
    {
        validator($data, $this->permissionRules())->validate();

        $data = $data['permissions'];

        try {

            $adminGroup = $this->model::findOrFail($id);
        } catch (Exception $e) {
            throw new AccidentException('操作失败，员工组不存在', Code::OPERATE_FAIL);
        }

        throw_if(!RouteMenuModel::isValid($data), new AccidentException('权限不存在！', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($adminGroup, $id, $data) {
            $routeMenus = RouteMenuModel::query()
                ->whereKey($data)
                ->select(['id', 'route_path', 'route_method'])
                ->get();

            $oldIds = [];
            foreach ($routeMenus as $routeMenu) {
                $path = $routeMenu->route_path;
                $method = $routeMenu->route_method;

                $old = Permission::query()
                    ->where('http_path', $path)
                    ->where('http_method', $method)
                    ->first()?->getKey();

                if ($old) {
                    $oldIds[] = ['admin_group_id' => $id, 'permission_id' => $old];
                }

                if ($method == 'PUT') {
                    $old = Permission::query()
                        ->where('http_path', $path)
                        ->where('http_method', 'PATCH')
                        ->first()?->getKey();

                    if ($old) {
                        $oldIds[] = ['admin_group_id' => $id, 'permission_id' => $old];
                    }
                }
            }

            DB::table('dsp_admin_group_permissions')->where('admin_group_id', $id)->delete();
            DB::table('dsp_admin_group_permissions')->insert($oldIds);

            return count($adminGroup->routeMenus()->sync($data)) > 0;
        });
    }

    public function getSuperAdminPermissions()
    {
        return RouteMenuModel::query()->where('parent_id', 0)->with('routes')->get();
    }

    /**
     * @return array
     */
    private function permissionRules(): array
    {
        return [
            'permissions' => 'required|array',
            'permissions.*' => 'required|integer',
        ];
    }
}
