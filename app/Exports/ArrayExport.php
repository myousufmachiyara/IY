<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{FromArray, WithHeadings, WithStrictNullComparison};

/** Strict null comparison keeps real zeros (¥0, 0 vehicles) as 0 instead of blank cells. */
class ArrayExport implements FromArray, WithHeadings, WithStrictNullComparison
{
    public function __construct(private array $rows, private array $headings) {}

    public function array(): array   { return $this->rows; }
    public function headings(): array { return $this->headings; }
}
