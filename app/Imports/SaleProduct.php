<?php



namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SaleProduct implements ToCollection
{
    public function collection(Collection $rows)
    {
        // Extract and work with specific columns (e.g., 'column1' and 'column2')
        $filteredData = $rows->map(function ($row) {
            return [
                'serial_no' => $row['serial_no'],
                'discount_name' => $row['discount_name'],
                'quantity' => $row['quantity'],
            ];
        });

        // You can now work with $filteredData, which contains only 'column1' and 'column2'
        // For example, you can pass it to your controller, return a response, or perform other actions
    }
}