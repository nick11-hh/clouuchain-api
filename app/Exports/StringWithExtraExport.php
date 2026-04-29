<?php

namespace App\Exports;

use App\Models\Admin;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class StringWithExtraExport implements WithMultipleSheets
{
    use Exportable;


    public function __construct(protected Collection $strings,protected Collection $languages)
    {

    }

    public function sheets(): array
    {
        return [
            new StringExport($this->strings),
            new LanguageExport($this->languages),
        ];
    }
}
