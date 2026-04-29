<?php

namespace App\Imports;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\Admin;
use App\Models\Supplier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Validators\Failure;

class SupplierImport implements ToModel, WithValidation, SkipsOnFailure, WithEvents, WithStartRow, WithMapping
{

    protected int $maxRows = 10000;

    protected int $rowCounter = 1;

    protected int $successCount = 0;

    protected int $failureCount = 0;

    protected array $failures = [];

    /**
     * @param int $maxRows
     */
    public function __construct(int $maxRows = 0)
    {
        if ($maxRows > 0) {
            $this->maxRows = $maxRows;
        }
    }

    /**
     * @param array $row
     * @return void
     */
    public function model(array $row)
    {
        $this->rowCounter++;
        if (empty(array_filter($row))) {
            return null;
        }
        $row['type'] = (int)$row['type'];
        $row['cooperation_level'] = (int)$row['cooperation_level'];
        $row['tax_point'] = (int)$row['tax_point'];
        $row['face_value'] = (int)$row['face_value'];
        $row['overall_rating'] = (int)$row['overall_rating'];
        $row['factory_scale'] = (int)$row['factory_scale'];
        if (empty($row['supplier_code'])) {
            //系统自动生成供应商编码
            $row['supplier_code'] = Supplier::generateSupplierCode();
        }
        $row['developer'] = auth()->id();

        // 验证数据，但不中断导入过程
        $validator = Validator::make($row, $this->getRules());
        if ($validator->fails()) {
            $this->failureCount++;
            $this->failures[] = [
                'row' => $this->rowCounter,
                'errors' => $validator->errors()->all(),
                'values' => $row
            ];
            return null;
        }

        $supplierData = Supplier::init($row);
        $exist = Supplier::query()->where('supplier_name', $row['supplier_name'])->orWhere('supplier_code', $row['supplier_code'])->first();
        if (!empty($exist)) {
            // 不抛出异常，而是记录错误并返回null
            $this->failureCount++;
            $this->failures[] = [
                'row' => $this->rowCounter,
                'errors' => ["供应商名称或供应商编码 -- {$supplierData['supplier_name']}({$supplierData['supplier_code']})重复"],
                'values' => $row
            ];
            return null;
        }
        Supplier::query()->create($supplierData);
        $this->successCount++;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->failureCount++;
            $this->failures[] = [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ];
        }
    }

    public function getImportResults()
    {
        return [
            'success_count' => $this->successCount,
            'failure_count' => $this->failureCount,
            'failures' => $this->failures
        ];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $highestRow = $event->sheet->getHighestRow();

                //减去标题行的行数为实际数据行
                $dataRow = $highestRow - 1;
                if ($dataRow > $this->maxRows) {
                    throw new AccidentException('批量导入失败，最多支持'. $this->maxRows .'行数据导入,请减少数据行重试', Code::OPERATE_FAIL);
                }
            }
        ];
    }

    public function map($row): array
    {
        if (count($row) < 5) {
            throw new AccidentException('The Excel data missing', Code::OPERATE_FAIL);
        }

        //返回的数据
        return [
            'supplier_name'            => trim($row[0]),
            'supplier_code'            => trim($row[1]),
            'type'                     => trim($row[2]),
            'supplier_qualification'   => trim($row[3]),
            'main_category'            => trim($row[4]),
            'certifications'           => trim($row[5]),
            'shipping_address'         => trim($row[6]),
            'supplier_url'             => trim($row[7]),
            'payment_terms'            => trim($row[8]),
            'cooperation_level'        => trim($row[9]),
            'service_and_after_sales'  => trim($row[10]),
            'tax_point'                => trim($row[11]),
            'face_value'               => trim($row[12]),
            'overall_rating'           => trim($row[13]),
            'person_in_charge'         => trim($row[14]),
            'person_in_charge_role'    => trim($row[15]),
            'contact_info'             => trim($row[16]),
            'factory_images'           => trim($row[17]),
            'factory_scale'            => trim($row[18]),
        ];
    }

    public function startRow(): int
    {
        return 2;
    }

    public function rules(): array
    {
        return [];
    }

    public function getRules()
    {
        return [
            'supplier_name' => 'required|string',
            'type' => 'required|int',
            'supplier_qualification' => 'required|string',
            'main_category' => 'required|string',
            'shipping_address' => 'required|string',
            'payment_terms' => 'required|string',
            'cooperation_level' => 'required|int',
            'service_and_after_sales' => 'required|string',
            'tax_point' => 'required|int',
            'face_value' => 'required|int',
            'overall_rating' => 'required|int',
            'person_in_charge' => 'required|string',
            'person_in_charge_role' => 'required|string',
            'contact_info' => 'required|string',
            'factory_scale' => 'required|int',
        ];
    }
}
