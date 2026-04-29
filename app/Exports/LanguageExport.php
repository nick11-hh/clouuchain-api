<?php

namespace App\Exports;

use App\Models\Admin;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class LanguageExport extends CommonExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize, WithTitle
{

    public function __construct(Enumerable $data)
    {
        parent::__construct($data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $header = [
            '国家',
            '语言编码',
        ];

        return array_map(function ($value) {
            return $value;
        }, $header);
    }

    /**
     * @param $string
     * @return array
     */
    public function map($string): array
    {
        return [
            $string->name,
            $string->language_code,
        ];
    }

    public function title(): string
    {
        return '语言编码';
    }
}
