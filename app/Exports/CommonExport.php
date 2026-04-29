<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

abstract class CommonExport extends DefaultValueBinder implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    protected $data;

    public function __construct(Enumerable $data)
    {
        $this->data = $data;
    }

    /**
     * @return Enumerable
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    abstract public function headings(): array;

    /**
     * @param  mixed  $package
     *
     * @return array
     */
    abstract public function map($package): array;


    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        // else return default behavior
        return parent::bindValue($cell, $value);
    }
}
