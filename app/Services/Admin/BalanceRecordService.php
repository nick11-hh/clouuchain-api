<?php

namespace App\Services\Admin;


use App\Jobs\Export\BalanceRecordExport;
use App\Lib\Code;
use App\Models\Order;
use App\Models\BalanceRecord;
use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\CustomBalance;
use App\Models\GoodsSku;
use App\Models\RechargeApply;
use App\Models\ChargeTypesModel;
use App\Models\AdminOperationLog;
use App\Services\Base\BalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ExcelExport;
use Illuminate\Support\Str;


class BalanceRecordService extends BaseService
{
    public $filterRules = [
        'custom_id'                 => ['=', 'custom_id'],
        'type'                      => ['=', 'type'],
        'source_type'               => ['=', 'source_type'],
        'created_at'                => ['between', ['begin_date', 'end_date']],
        // 'order_sn'                  => ['like', 'order_sn'],
        'custom:group_id'           => ['=', 'group_id'],
        'custom:customer_number'    => ['like', 'customer_number'],
        'serial_no'                 => ['=', 'serial_no'],
        'out_serial_no'             => ['=', 'out_serial_no'],
    ];

    private ClientGoodsSku $skuModel;

    public function __construct()
    {
        $this->model = new BalanceRecord();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        if (isset($this->formData['order_sn']) && !empty($this->formData['order_sn'])) {
            $orderIds = Order::query()->where('order_id', $this->formData['order_sn'])
                ->orWhere('name', $this->formData['order_sn'])->select('id')->get()->pluck('id')->toArray();
            $this->query->where(function ($query) use ($orderIds) {
                $query->whereIn('relation_id', $orderIds)->orWhere('order_sn', $this->formData['order_sn']);
            });
        }

        if(isset($this->formData['custom_id_str']) && !empty($this->formData['custom_id_str'])){
            $this->query->where('custom_id', $this->formData['custom_id_str']);
        }
        // 操作员工
        if(isset($this->formData['operate_admin_id']) && !empty($this->formData['operate_admin_id'])){
            $this->query->where('operate_admin_id', $this->formData['operate_admin_id']);
        }

        // 批量搜索
        if (!empty($this->formData['batch_keyword'])) {
            $keywordArr = explode("\n", $this->formData['batch_keyword']);
            $orderIds = Order::query()->whereIn('order_id', $keywordArr)
                ->orWhere('name', $keywordArr)->select('id')->get()->pluck('id')->toArray();
            $this->query->where(function ($query) use ($orderIds) {
                $query->whereIn('relation_id', $orderIds);
            });
        }

        $this->query->with(['custom', 'order:id,order_id,name', 'admin:id,name']);
        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['custom'])->findOrFail($id);
    }

    /**
     * 导出
     * @return true
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:20
     */
    public function export()
    {
        ini_set('memory_limit', '512M');
        $fileName = 'BalanceRecord_'. Carbon::now()->format('YmdHis') . '_'. Str::random(6) . '.xlsx';

        $page_type = isset($this->formData['page_type']) ? $this->formData['page_type'] : 1;
        if ($page_type == 10) {
            $type = 10;
            $data = $this->getExportDatum();
        } else {
            $type = 1;
            $data = $this->getExportData();
        }

        /** @var $excelExport ExcelExport */
        $excelExport = ExcelExport::query()->create([
            'name'  => $fileName,
            'type'  => ExcelExport::TYPE_BALANCE_RECORD,
            'url'   => '',
        ]);

        dispatch(new BalanceRecordExport($excelExport, $data, $type));
        return true;
    }

    /**
     * 获取导出数据
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:24
     */
    public function getExportData()
    {
        $this->query->with(['custom.customGroup', 'order:id,order_id,name']);

        $this->query->latest('id');
        $this->setFilter();
        $data = [];
        $this->query->chunk(100, function ($items) use (&$data) {
            foreach ($items as $item) {
                if (!in_array($item->serial_no, array_column($data, 'serial_no'))) {
                    $orderSnParts = explode(' / ', trim(in_array($item->source_type, [BalanceRecord::SOURCE_ORDER_PAY, BalanceRecord::SOURCE_ORDER_REFUND, BalanceRecord::SOURCE_SUPPLEMENT_FEE]) ? $item->order?->order_id . ' / ' . $item->order?->name : $item->order_sn, ' / '));
                    $data[] = [
                        'serial_no'         => $item->serial_no,
                        'custom_id'         => $item->custom_id,
                        'customer_number'   => $item->custom->customer_number ?? '',
                        'custom_name'       => $item->custom->custom_name ?? '',
                        'group_name'        => $item->custom->customGroup ? $item->custom->customGroup->group_name : '',
                        'source_type_name'  => $item->source_type_name,
                        'amount'            => ($item->type == 1 ? '+' : '-') . bcdiv($item->amount, 100, 2),
                        'after_change_balance' => bcdiv($item->after_change_balance, 100, 2),
                        'order_sn'          => $orderSnParts[0] ?? '',
                        'order_name'        => $orderSnParts[1] ?? '',
                        'remark'            => $item->remark,
                        'created_at'        => (string) $item->created_at,
                    ];
                }
            }
        });

        return $data;
    }

    /**
     * 导出流水明细数据
     * @return array
     */
    public function getExportDatum()
    {
        $this->query->with(['custom.customGroup', 'admin:id,name'])
            ->where('custom_id', $this->formData['custom_id_str'])
            ->latest('id');
        $this->setFilter();
        $data = [];
        $this->query->chunk(100, function ($items) use (&$data) {
            $credit_line = $frozen_limit = 0;
            foreach ($items as $item) {
                if (!in_array($item->serial_no, array_column($data, 'serial_no'))) {
                    if ($item->source_type == 10) {
                        $credit_line = bcdiv($item->actual_amount, 100, 2);
                    }
                    if ($item->source_type == 11) {
                        $frozen_limit = bcdiv($item->actual_amount, 100, 2);
                    }
                    $data[] = [
                        'serial_no'         => $item->serial_no,
                        'customer_number'   => $item->custom->customer_number ?? '',
                        'custom_name'       => $item->custom->custom_name ?? '',
                        'group_name'        => $item->custom->customGroup ? $item->custom->customGroup->group_name : '',
                        'source_type_name'  => $item->source_type_name,
                        'amount'            => ($item->type == 1 ? '+' : '-') . bcdiv($item->amount, 100, 2),
                        'after_change_balance' => bcdiv($item->after_change_balance, 100, 2),
                        'credit_line' => $credit_line,
                        'frozen_limit' => $frozen_limit,
                        'remark'            => $item->remark,
                        'created_at'        => (string) $item->created_at,
                    ];
                }
            }
        });

        return $data;
    }

    /**
     * 手动操作余额
     * @throws \Exception
     */
    public function manuallyOperateBalance($params)
    {
        validator($params, [
            'customer_id'       => 'required|int',
            'amount'            => 'required|numeric',
            'charge_type_id'    => 'required|int',
            'remark'            => 'required|string',
            'order_sn'          => 'sometimes|nullable',
            'type'              => 'sometimes|nullable',
            'attachment_files'  => 'sometimes|nullable|array',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $type   = $params['type'] ?? 1;
            $record = false;

            $params['operate_admin_id'] = getAdminId();

            $balanceService = new BalanceService($params['customer_id']);

            if ($type == 1) {

                $chargeType = ChargeTypesModel::where('id', $params['charge_type_id'])->value('name');

                AdminOperationLog::create([
                    'type' => AdminOperationLog::TYPE_1,
                    'opt_type' => AdminOperationLog::OPT_TYPE_3,
                    'admin_id' => $params['operate_admin_id'],
                    'custom_id' => $params['customer_id'],
                    'description' => '后台手动扣款，金额【' . $params['amount'] . '】，费用类型【' . $chargeType . '】，扣款说明【' . $params['remark'] . '】，关联单号【' . ($params['order_sn'] ?? '') . '】',
                ]);

                $record = $balanceService
                    ->setRemark($params['remark'] ?: '后台手动扣款')
                    ->setAttachmentFiles($params['attachment_files'] ?? [])
                    ->deduction(
                        $params['amount'],
                        BalanceRecord::SOURCE_MANUALLY_DEDUCT,
                        $params['order_sn'] ?? '',
                        $params
                    );

            }

            return $record;
        });


    }

    /**
     * 批量添加明细
     */
    public function batchAddBreakdown($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'cost_breakdown' => 'sometimes|nullable|string',
        ])->validate();

        return DB::transaction(function () use ($params) {
            $this->query->whereIn('id', $params['ids'])->update([
                'cost_breakdown' => $params['cost_breakdown'] ?? '',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        });
    }

}
