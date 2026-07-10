<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithHeadings};

class ArrayExport implements FromArray, WithHeadings
{
    public function __construct(private array $rows, private array $headings) {}

    public function array(): array   { return $this->rows; }
    public function headings(): array { return $this->headings; }
}