<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SaleProduct implements ToCollection
{
    public function collection(Collection $rows)
    {
        $filteredData = $rows->map(function ($row) {
            $price = $row['price'] ?? null; // Check if 'price' column exists in the row

            // If 'price' column exists and has a value, use it, otherwise, fetch from product
            if ($price !== null) {
                return [
                    'serial_no' => $row['serial_no'],
                    'discount_name' => $row['discount_name'],
                    'quantity' => $row['quantity'],
                    'price' => $price, // Use price from Excel
                ];
            } else {
                return [
                    'serial_no' => $row['serial_no'],
                    'discount_name' => $row['discount_name'],
                    'quantity' => $row['quantity'],
                ];
            }
        });

        // You can now work with $filteredData, which contains only the required columns
        // For example, you can pass it to your controller, return a response, or perform other actions
    }
}
