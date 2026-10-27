<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SaleProduct implements ToCollection
{
    public function collection(Collection $collection): void
    {
    }
}
