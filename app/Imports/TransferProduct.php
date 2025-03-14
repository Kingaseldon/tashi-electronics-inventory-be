<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class TransferProduct implements ToCollection
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        $filteredData = $rows->map(function ($row) {

            return [
                'serial_no' => $row['serial_no'],
                'transfer_quantity' => $row['transfer_quantity']
            ];
        });

        // You can now work with $filteredData, which contains only the required columns
        // For example, you can pass it to your controller, return a response, or perform other actions
    }
}
