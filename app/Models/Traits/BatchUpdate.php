<?php

namespace App\Models\Traits;

use App\Http\Traits\SqlLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

trait BatchUpdate
{
    use SqlLog;
    public static $useOrigin = false;
    //批量更新

    public static function changeUseOrigin($value = false)
    {
        self::$useOrigin = $value;
    }

    public function updateBatch(Collection $updateData)
    {
        self::changeUseOrigin(true);

        $updateData->map(function ($raw) {
            $raw->setAppends([]);
            return $raw;
        });

        $multipleData = $updateData->toArray();
        try {
            if (empty($multipleData)) {
                throw new AccidentException('数据不能为空');
            }
            $tableName = $this->getTable(); // 表名
            $firstRow = current($multipleData);
            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = 'UPDATE ' . $tableName . ' SET ';
            $sets = [];
            $bindings = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = '`' . $uColumn . '` = CASE ';
                foreach ($multipleData as $data) {
                    $setSql .= 'WHEN `' . $referenceColumn . '` = ? THEN ? ';
                    $bindings[] = $data[$referenceColumn];
                    if (is_array($data[$uColumn])) {
                        $bindings[] = json_encode($data[$uColumn]);
                    } else {
                        $bindings[] = $data[$uColumn];
                    }
                }
                $setSql .= 'ELSE `' . $uColumn . '` END ';
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings = array_merge($bindings, $whereIn);
            $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ', ') . ' WHERE `' . $referenceColumn . '` IN (' . $whereIn . ')';
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            app('log')->channel('single')->debug('当前批量更新出错,错误原因为:' . $e->getMessage());
            return false;
        }
    }
}
