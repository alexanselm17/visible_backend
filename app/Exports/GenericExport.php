<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class GenericExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings
{
    protected Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    // Force column B (phone) to be written as TEXT so leading + stays
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'B') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        // Ensure phone is a string and includes + if that’s your format
        return $this->data->map(function ($row) {
            // $row[1] == phone column
            if (isset($row[1]) && $row[1] !== '') {
                $phone = (string) $row[1];
                // add + if it should be there and isn’t already
                if ($phone[0] !== '+') {
                    // adjust this rule to your needs
                    $phone = '+'.$phone;
                }
                $row[1] = $phone;
            }

            return $row;
        });
    }

    public function headings(): array
    {
        return ['PayeeName', 'PayeeMobileNo', 'Amount', 'Reference'];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,        // phone as text
            'C' => NumberFormat::FORMAT_NUMBER_00,   // amount
        ];
    }
}
